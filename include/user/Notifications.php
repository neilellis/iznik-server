<?php

require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/misc/Entity.php');
require_once(IZNIK_BASE . '/include/misc/Log.php');
require_once(IZNIK_BASE . '/include/user/User.php');
require_once(IZNIK_BASE . '/include/newsfeed/Newsfeed.php');
require_once(IZNIK_BASE . '/mailtemplates/notifications/notificationsoff.php');

class Notifications
{
    const TYPE_COMMENT_ON_YOUR_POST = 'CommentOnYourPost';
    const TYPE_COMMENT_ON_COMMENT = 'CommentOnCommented';
    const TYPE_LOVED_POST = 'LovedPost';
    const TYPE_LOVED_COMMENT = 'LovedComment';
    const TYPE_TRY_FEED = 'TryFeed';
    const TYPE_MEMBERSHIP_PENDING = 'MembershipPending';
    const TYPE_MEMBERSHIP_APPROVED = 'MembershipApproved';
    const TYPE_MEMBERSHIP_REJECTED = 'MembershipRejected';
    const TYPE_ABOUT_ME = 'AboutMe';

    private $dbhr, $dbhm, $log;

    function __construct(LoggedPDO $dbhr, LoggedPDO $dbhm)
    {
        $this->dbhr = $dbhr;
        $this->dbhm = $dbhm;
        $this->log = new Log($dbhr, $dbhm);
    }

    public function countUnseen($userid) {
        $counts = $this->dbhr->preQuery("SELECT COUNT(*) AS count FROM users_notifications WHERE touser = ? AND seen = 0;", [
            $userid
        ]);
        return($counts[0]['count']);
    }

    private function snip(&$msg) {
        if ($msg) {
            if (strlen($msg) > 57) {
                $msg = wordwrap($msg, 60);
                $p = strpos($msg, "\n");
                $msg = $p !== FALSE ? substr($msg, 0, $p) : $msg;
                $msg .= '...';
            }
        }
    }

    public function get($userid, &$ctx) {
        $ret = [];
        $idq = $ctx && pres('id', $ctx) ? (" AND id < " . intval($ctx['id'])) : '';
        $sql = "SELECT * FROM users_notifications WHERE touser = ? $idq ORDER BY id DESC LIMIT 10;";
        $notifs = $this->dbhr->preQuery($sql, [ $userid ]);

        foreach ($notifs as &$notif) {
            $notif['timestamp'] = ISODate($notif['timestamp']);

            if (pres('fromuser', $notif)) {
                $u = User::get($this->dbhr, $this->dbhm, $notif['fromuser']);
                $notif['fromuser'] = $u->getPublic(NULL, FALSE, FALSE, $ctx, FALSE, FALSE, FALSE, FALSE, FALSE);
            }

            if (pres('newsfeedid', $notif)) {
                $nots = $this->dbhr->preQuery("SELECT * FROM newsfeed WHERE id = ?;", [
                    $notif['newsfeedid']
                ]);

                foreach ($nots as $not) {
                    unset($not['position']);
                    $this->snip($not['message']);

                    if (pres('deleted', $not)) {
                        # This item has been deleted - don't show the corresponding notification.
                        if (!$notif['seen']) {
                            # This notification hasn't been seen, and would therefore show in the count. Mark it
                            # as seen for next time.
                            $this->dbhm->background("UPDATE users_notifications SET seen = 1 WHERE id = {$notif['id']}");
                        }

                        $notif = NULL;
                    } else {
                        if ($not['replyto']) {
                            $origs = $this->dbhr->preQuery("SELECT * FROM newsfeed WHERE id = ?;", [
                                $not['replyto']
                            ]);

                            foreach ($origs as &$orig) {
                                $this->snip($orig['message']);
                                unset($orig['position']);
                                $not['replyto'] = $orig;
                            }
                        }

                        unset($not['position']);
                        $notif['newsfeed'] = $not;

                        if (pres('deleted', $not['replyto'])) {
                            # This notification is for a newsfeed item which is in a deleted thread.  Don't show it.

                            if (!$notif['seen']) {
                                # This notification hasn't been seen, and would therefore show in the count. Mark it
                                # as seen for next time.
                                $this->dbhm->background("UPDATE users_notifications SET seen = 1 WHERE id = {$notif['id']}");
                            }

                            $notif = NULL;
                        }
                    }
                }
            }

            if ($notif) {
                $ret[] = $notif;
            }

            $ctx = [
                'id' => $notif['id']
            ];
        }

        return($ret);
    }

    public function add($from, $to, $type, $newsfeedid, $newsfeedthreadid = NULL, $url = NULL) {
        $id = NULL;

        if ($from != $to) {
            $n = new Newsfeed($this->dbhr, $this->dbhm);

            # For newsfeed items, ensure we don't notify if we've unfollowed.
            if (!$newsfeedthreadid || !$n->unfollowed($to, $newsfeedthreadid)){
                $sql = "INSERT INTO users_notifications (`fromuser`, `touser`, `type`, `newsfeedid`, `url`) VALUES (?, ?, ?, ?, ?);";
                $this->dbhm->preExec($sql, [ $from, $to, $type, $newsfeedid, $url ]);
                $id = $this->dbhm->lastInsertId();

                $p = new PushNotifications($this->dbhr, $this->dbhm);
                $p->notify($to, MODTOOLS);
            }
        }

        return($id);
    }

    public function seen($userid, $id = NULL) {
        $idq = $id ? (" AND id = " . intval($id)) : '';
        $sql = "UPDATE users_notifications SET seen = 1 WHERE touser = ? $idq;";
        $rc = $this->dbhm->preExec($sql, [ $userid ] );

        $p = new PushNotifications($this->dbhr, $this->dbhm);
        $p->notify($userid, MODTOOLS);

        return($rc);
    }

    public function off($uid) {
        $u = User::get($this->dbhr, $this->dbhm, $uid);

        $settings = json_decode($u->getPrivate('settings'), TRUE);

        if (presdef('notificationmails', $settings, TRUE)) {
            $settings['notificationmails'] = FALSE;
            $u->setPrivate('settings', json_encode($settings));

            $this->log->log([
                'type' => Log::TYPE_USER,
                'subtype' => Log::SUBTYPE_NOTIFICATIONOFF,
                'user' => $uid
            ]);

            $email = $u->getEmailPreferred();

            if ($email) {
                list ($transport, $mailer) = getMailer();
                $html = notifications_off(USER_SITE, USERLOGO);

                $message = Swift_Message::newInstance()
                    ->setSubject("Email Change Confirmation")
                    ->setFrom([NOREPLY_ADDR => SITE_NAME])
                    ->setReturnPath($u->getBounce())
                    ->setTo([ $email => $u->getName() ])
                    ->setBody("Thanks - we've turned off the mails for notifications.");

                # Add HTML in base-64 as default quoted-printable encoding leads to problems on
                # Outlook.
                $htmlPart = Swift_MimePart::newInstance();
                $htmlPart->setCharset('utf-8');
                $htmlPart->setEncoder(new Swift_Mime_ContentEncoder_Base64ContentEncoder);
                $htmlPart->setContentType('text/html');
                $htmlPart->setBody($html);
                $message->attach($htmlPart);

                $this->sendIt($mailer, $message);
            }
        }
    }

    public function sendIt($mailer, $message) {
        $mailer->send($message);
    }

    public function sendEmails($userid = NULL, $before = '24 hours ago', $since = '7 days ago', $unseen = TRUE) {
        $loader = new Twig_Loader_Filesystem(IZNIK_BASE . '/mailtemplates/twig');
        $twig = new Twig_Environment($loader);

        $userq = $userid ? " AND `touser` = $userid " : '';

        $mysqltime = date("Y-m-d H:i:s", strtotime($before));
        $mysqltime2 = date("Y-m-d H:i:s", strtotime($since));
        $seenq = $unseen ? " AND seen = 0 ": '';
        $sql = "SELECT DISTINCT(touser) FROM `users_notifications` WHERE timestamp <= '$mysqltime' AND timestamp >= '$mysqltime2' $seenq AND `type` != ? $userq;";
        $users = $this->dbhr->preQuery($sql, [
            Notifications::TYPE_TRY_FEED
        ]);

        $total = 0;

        foreach ($users as $user) {
            $count = 0;
            $u = new User($this->dbhr, $this->dbhm, $user['touser']);
            error_log("Consider {$user['touser']} email " . $u->getEmailPreferred());
            if ($u->sendOurMails() && $u->getSetting('notificationmails', TRUE)) {
                error_log("...send");
                $ctx = NULL;
                $notifs = $this->get($user['touser'], $ctx);

                $str = '';
                $twignotifs = [];

                foreach ($notifs as &$notif) {
                    if ((!$unseen || !$notif['seen']) && $notif['type'] != Notifications::TYPE_TRY_FEED) {
                        #error_log("Message is {$notif['newsfeed']['message']} len " . strlen($notif['newsfeed']['message']));
                        $fromname = ($notif['fromuser'] ? "{$notif['fromuser']['displayname']}" : "Someone");
                        $notif['fromname'] = $fromname;
                        $notif['timestamp'] = date("D, jS F g:ia", strtotime($notif['timestamp']));
                        $twignotifs[] = $notif;

                        switch ($notif['type']) {
                            case Notifications::TYPE_COMMENT_ON_COMMENT:
                                $str .= $fromname . " replied to your comment: {$notif['newsfeed']['message']}\n";
                                $count++;
                                break;
                            case Notifications::TYPE_COMMENT_ON_YOUR_POST:
                                $str .= $fromname . " commented on your post: {$notif['newsfeed']['message']}\n";
                                $count++;
                                break;
                            case Notifications::TYPE_LOVED_POST:
                                $str .= $fromname . " loved your post '{$notif['newsfeed']['message']}'\n";
                                $count++;
                                break;
                            case Notifications::TYPE_LOVED_COMMENT:
                                $str .= $fromname . " loved your comment '{$notif['newsfeed']['message']}'\n";
                                $count++;
                                break;
                            case Notifications::TYPE_MEMBERSHIP_PENDING:
                                $str .= $fromname . " your application to {$notif['url']} requires approval; we'll let you know soon.\n";
                                $count++;
                                break;
                            case Notifications::TYPE_MEMBERSHIP_APPROVED:
                                $str .= $fromname . " your application to {$notif['url']} was approved; you can now use the community.\n";
                                $count++;
                                break;
                            case Notifications::TYPE_MEMBERSHIP_REJECTED:
                                $str .= $fromname . " your application to {$notif['url']} was denied.\n";
                                $count++;
                                break;
                            case Notifications::TYPE_ABOUT_ME:
                                $str .= "Why not introduce yourself to other freeglers by telling us a bit about you?  You'll get a better response and it makes freegling more fun.\n";
                                $count++;
                                break;
                        }
                    }
                }

                $url = $u->loginLink(USER_SITE, $user['touser'], '/newsfeed', 'notifemail');
                $noemail = 'notificationmailsoff-' . $user['touser'] . "@" . USER_DOMAIN;

                try {
                    $html = $twig->render('notifications/email.html', [
                        'count' => count($twignotifs),
                        'notifications'=> $twignotifs,
                        'settings' => $u->loginLink(USER_SITE, $u->getId(), '/settings', User::SRC_NOTIFICATIONS_EMAIL),
                        'email' => $u->getEmailPreferred(),
                        'noemail' => $noemail
                    ]);
                } catch (Exception $e) {
                    error_log("Message prepare failed with " . $e->getMessage());
                }

                $message = Swift_Message::newInstance()
                    ->setSubject("You have " . ($count ? $count : '') . " new notification" . ($count != 1 ? 's' : ''))
                    ->setFrom([NOREPLY_ADDR => 'Freegle'])
                    ->setReturnPath($u->getBounce())
                    ->setTo([ $u->getEmailPreferred() => $u->getName() ])
                    ->setBody("\r\n\r\nPlease click here to read them: $url");

                # Add HTML in base-64 as default quoted-printable encoding leads to problems on
                # Outlook.
                $htmlPart = Swift_MimePart::newInstance();
                $htmlPart->setCharset('utf-8');
                $htmlPart->setEncoder(new Swift_Mime_ContentEncoder_Base64ContentEncoder);
                $htmlPart->setContentType('text/html');
                $htmlPart->setBody($html);
                $message->attach($htmlPart);

                list ($transport, $mailer) = getMailer();
                $this->sendIt($mailer, $message);

                $total += $count;
            }
        }

        return($total);
    }
}
