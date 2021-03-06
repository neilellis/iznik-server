<?php

require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/misc/Entity.php');
require_once(IZNIK_BASE . '/include/user/User.php');
require_once(IZNIK_BASE . '/include/user/Address.php');
require_once(IZNIK_BASE . '/include/message/Message.php');
require_once(IZNIK_BASE . '/include/chat/ChatRoom.php');
require_once(IZNIK_BASE . '/include/user/Schedule.php');
require_once(IZNIK_BASE . '/include/spam/Spam.php');

class ChatMessage extends Entity
{
    /** @var  $dbhm LoggedPDO */
    var $publicatts = array('id', 'chatid', 'userid', 'date', 'message', 'system', 'refmsgid', 'type', 'seenbyall', 'mailedtoall', 'reviewrequired', 'reviewedby', 'reviewrejected', 'spamscore', 'reportreason', 'refchatid', 'imageid');
    var $settableatts = array('name');

    const TYPE_DEFAULT = 'Default';
    const TYPE_MODMAIL = 'ModMail';
    const TYPE_SYSTEM = 'System';
    const TYPE_INTERESTED = 'Interested';
    const TYPE_PROMISED = 'Promised';
    const TYPE_RENEGED = 'Reneged';
    const TYPE_REPORTEDUSER = 'ReportedUser';
    const TYPE_COMPLETED = 'Completed';
    const TYPE_IMAGE = 'Image';
    const TYPE_ADDRESS = 'Address';
    const TYPE_NUDGE = 'Nudge';
    const TYPE_SCHEDULE = 'Schedule';
    const TYPE_SCHEDULE_UPDATED = 'ScheduleUpdated';

    const ACTION_APPROVE = 'Approve';
    const ACTION_APPROVE_ALL_FUTURE = 'ApproveAllFuture';
    const ACTION_REJECT = 'Reject';
    const ACTION_HOLD = 'Hold';
    const ACTION_RELEASE = 'Release';

    /** @var  $log Log */
    private $log;

    function __construct(LoggedPDO $dbhr, LoggedPDO $dbhm, $id = NULL, $fetched = NULL)
    {
        $this->fetch($dbhr, $dbhm, $id, 'chat_messages', 'chatmessage', $this->publicatts, $fetched);
        $this->log = new Log($dbhr, $dbhm);
    }

    /**
     * @param LoggedPDO $dbhm
     */
    public function setDbhm($dbhm)
    {
        $this->dbhm = $dbhm;
    }

    public function whitelistURLs($message) {
        global $urlPattern, $urlBad;

        if (preg_match_all($urlPattern, $message, $matches)) {
            $me = whoAmI($this->dbhr, $this->dbhm);
            $myid = $me ? $me->getId() : NULL;

            foreach ($matches as $val) {
                foreach ($val as $url) {
                    $bad = FALSE;
                    $url2 = str_replace('http:', '', $url);
                    $url2 = str_replace('https:', '', $url2);
                    foreach ($urlBad as $badone) {
                        if (strpos($url2, $badone) !== FALSE) {
                            $bad = TRUE;
                        }
                    }

                    #error_log("Whitelist $url bad $bad");
                    if (!$bad && strlen($url) > 0) {
                        $url = substr($url, strpos($url, '://') + 3);
                        $p = strpos($url, '/');
                        $domain = $p ? substr($url, 0, $p) : $url;
                        $this->dbhm->preExec("INSERT INTO spam_whitelist_links (userid, domain) VALUES (?, ?) ON DUPLICATE KEY UPDATE count = count + 1;", [
                            $myid,
                            $domain
                        ]);
                    }
                }
            }
        }
    }

    public function checkReview($message, $language = FALSE) {
        $s = new Spam($this->dbhr, $this->dbhm);
        $ret = $s->checkReview($message, $language);

        return($ret);
    }

    public function checkSpam($message) {
        $s = new Spam($this->dbhr, $this->dbhm);
        return($s->checkSpam($message) !== NULL);
    }

    public function chatByEmail($chatmsgid, $msgid) {
        if ($chatmsgid && $msgid) {
            # We record the link between a chat message an originating email in case we need it when reviewing in chat.
            $this->dbhm->preExec("INSERT INTO chat_messages_byemail (chatmsgid, msgid) VALUES (?, ?);", [
                $chatmsgid, $msgid
            ]);
        }
    }

    public function checkDup($chatid, $userid, $message, $type = ChatMessage::TYPE_DEFAULT, $refmsgid = NULL, $platform = TRUE, $spamscore = NULL, $reportreason = NULL, $refchatid = NULL, $imageid = NULL, $facebookid = NULL) {
        $dup = NULL;

        # Check last message in the chat to see whether we have a duplicate.
        $lasts = $this->dbhr->preQuery("SELECT * FROM chat_messages WHERE chatid = ? ORDER BY id DESC LIMIT 1;", [
            $chatid
        ]);

        foreach ($lasts as $last) {
            if ($userid == $last['userid'] &&
                $type == $last['type'] &&
                $last['message'] == $message &&
                $refmsgid == $last['refmsgid'] &&
                $refchatid == $last['refchatid'] &&
                $imageid == $last['imageid'] &&
                $facebookid == $last['facebookid']) {
                $dup = $last['id'];
            }
        }

        return($dup);
    }

    public function create($chatid, $userid, $message, $type = ChatMessage::TYPE_DEFAULT, $refmsgid = NULL, $platform = TRUE, $spamscore = NULL, $reportreason = NULL, $refchatid = NULL, $imageid = NULL, $facebookid = NULL) {
        try {
            $review = 0;
            $spam = 0;
            $blocked = FALSE;

            $r = new ChatRoom($this->dbhr, $this->dbhm, $chatid);
            $chattype = $r->getPrivate('chattype');
            $u = new User($this->dbhr, $this->dbhm, $userid);

            # Mods may need to refer to spam keywords in replies.  We should only check chat messages of types which
            # include user text.
            #
            # We also don't want to check for spam in chats between users and mods.
            if ($chattype != ChatRoom::TYPE_USER2MOD &&
                !$u->isModerator() &&
                $u->getPrivate('chatmodstatus') == User::CHAT_MODSTATUS_MODERATED &&
                ($type === ChatMessage::TYPE_DEFAULT || $type === ChatMessage::TYPE_INTERESTED || $type === ChatMessage::TYPE_REPORTEDUSER)) {
                $review = $this->checkReview($message, TRUE);
                $spam = $this->checkSpam($message) || $this->checkSpam($u->getName());

                # If we decided it was spam then it doesn't need reviewing.
                $review = $spam ? 0 : $review;
            }

            # Even if it's spam, we still create the message, so that if we later decide that it wasn't spam after all
            # it's still around to unblock.
            $rc = $this->dbhm->preExec("INSERT INTO chat_messages (chatid, userid, message, type, refmsgid, platform, reviewrequired, reviewrejected, spamscore, reportreason, refchatid, imageid, facebookid) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?);", [
                $chatid,
                $userid,
                $message,
                $type,
                $refmsgid,
                $platform,
                $review,
                $spam,
                $spamscore,
                $reportreason,
                $refchatid,
                $imageid,
                $facebookid
            ]);

            $id = $this->dbhm->lastInsertId();

            # We have ourselves seen this message.
            $this->dbhm->preExec("UPDATE chat_roster SET lastmsgseen = ?, lastmsgemailed = ? WHERE chatid = ? AND userid = ? AND (lastmsgseen IS NULL OR lastmsgseen < ?);",
                [
                    $id,
                    $id,
                    $chatid,
                    $userid,
                    $id
                ]);

            $r = new ChatRoom($this->dbhr, $this->dbhm, $chatid);
            $r->updateMessageCounts();

            # Update the reply time now we've replied.
            $r->replyTime($userid, TRUE);

            if ($chattype == ChatRoom::TYPE_USER2USER || $chattype == ChatRoom::TYPE_USER2MOD) {
                # If anyone has closed this chat so that it no longer appears in their list, we want to open it again.
                # If they have blocked it, we don't want to notify them.
                #
                # This is rare, so rather than do an UPDATE which would always be a bit expensive even if we have
                # nothing to do, we do a SELECT to see if there are any.
                $closeds = $this->dbhr->preQuery("SELECT id, status FROM chat_roster WHERE chatid = ? AND status IN (?, ?);", [
                    $chatid,
                    ChatRoom::STATUS_CLOSED,
                    ChatRoom::STATUS_BLOCKED
                ], FALSE, FALSE);

                foreach ($closeds as $closed) {
                    if ($closed['status'] == ChatRoom::STATUS_CLOSED) {
                        $this->dbhm->preExec("UPDATE chat_roster SET status = ? WHERE id = ?;", [
                            ChatRoom::STATUS_OFFLINE,
                            $closed['id']
                        ]);
                    } else if ($closed['status'] == ChatRoom::STATUS_BLOCKED) {
                        $blocked = TRUE;
                    }
                }
            }

            if ($chattype == ChatRoom::TYPE_USER2USER) {
                # If we have created a message, then any outstanding nudge to us has now been dealt with.
                $other = $r->getPrivate('user1') == $userid ? $r->getPrivate('user2') : $r->getPrivate('user1');
                $this->dbhm->background("UPDATE users_nudges SET responded = NOW() WHERE fromuser = $other AND touser = $userid AND responded IS NULL;");
            }

            if (!$spam && !$review && !$blocked) {
                $r->pokeMembers();
                $r->notifyMembers($u->getName(), $message, $userid);

                if ($r->getPrivate('synctofacebook') == ChatRoom::FACEBOOK_SYNC_REPLIED_ON_FACEBOOK) {
                    # We have had a reply from Facebook, which caused us to flag this conversation.
                    # This is now the first reply from the other user.  So we want to post a link on Facebook which
                    # will allow the user on there to read the message we've just created.  Set the state to
                    # make this happen in the background.
                    $r->setPrivate('synctofacebook', ChatRoom::FACEBOOK_SYNC_REPLIED_ON_PLATFORM);
                }
            }
        } catch (Exception $e) {
            error_log("Failed to create chat " . $e->getMessage() . " at " . $e->getFile() . " line " . $e->getLine());
            $id = NULL;
            $rc = 0;
        }

        if ($rc && $id) {
            $this->fetch($this->dbhm, $this->dbhm, $id, 'chat_messages', 'chatmessage', $this->publicatts);
            return($id);
        } else {
            return(NULL);
        }
    }

    public function getPublic($refmsgsummary = FALSE) {
        $ret = $this->getAtts($this->publicatts);

        if (pres('refmsgid', $ret)) {
            # There is a message (in the sense of an item rather than a chat message) attached to this chat message.
            $locationlist = NULL;
            $userlist = NULL;
            $m = new Message($this->dbhr, $this->dbhm , $ret['refmsgid']);
            $ret['refmsg'] = $m->getPublic(FALSE, FALSE, FALSE, $userlist, $locationlist, $refmsgsummary);
            unset($ret['refmsgid']);
            unset($ret['refmsg']['textbody']);
            unset($ret['refmsg']['htmlbody']);
            unset($ret['refmsg']['message']);
        }

        if (pres('imageid', $ret)) {
            # There is an image attached
            $a = new Attachment($this->dbhr, $this->dbhm, $ret['imageid'], Attachment::TYPE_CHAT_MESSAGE);
            $ret['image'] = [
                'id' => $ret['imageid'],
                'path' => $a->getPath(FALSE),
                'paththumb' => $a->getPath(TRUE)
            ];
            unset($ret['imageid']);
        }

        if ($ret['type'] == ChatMessage::TYPE_ADDRESS) {
            $id = intval($ret['message']);
            $ret['message'] = NULL;
            $a = new Address($this->dbhr, $this->dbhm, $id);
            $ret['address'] = $a->getPublic();
        }

        if ($ret['type'] == ChatMessage::TYPE_SCHEDULE || $ret['type'] == ChatMessage::TYPE_SCHEDULE_UPDATED) {
            # We want to return the currently matching dates.
            $s = new Schedule($this->dbhr, $this->dbhm);
            $r = new ChatRoom($this->dbhr, $this->dbhm, $this->chatmessage['chatid']);
            $me = whoAmI($this->dbhr, $this->dbhm);

            if ($me) {
                $myid = $me->getId();
                $user1 = $r->getPrivate('user1');
                $user2 = $r->getPrivate('user2');
                $other = $myid == $user1 ? $user2 : $user1;
                $ret['matches'] = $s->match($myid, $other);
            }
        }

        # Strip any remaining quoted text in replies.
        $ret['message'] = trim(preg_replace('/\|.*$/m', "", $ret['message']));
        $ret['message'] = trim(preg_replace('/\>.*$/m', "", $ret['message']));
        $ret['message'] = trim(preg_replace('/\#yiv.*$/m', "", $ret['message']));

        return($ret);
    }

    public function approve($id) {
        $me = whoAmI($this->dbhr, $this->dbhm);
        $myid = $me ? $me->getId() : NULL;

        # We can only approve if we can see this message for review.
        $sql = "SELECT chat_messages.* FROM chat_messages INNER JOIN chat_rooms ON reviewrequired = 1 AND chat_rooms.id = chat_messages.chatid INNER JOIN memberships ON memberships.userid = (CASE WHEN chat_messages.userid = chat_rooms.user1 THEN chat_rooms.user2 ELSE chat_rooms.user1 END) AND memberships.groupid IN (SELECT groupid FROM memberships WHERE memberships.userid = ? AND memberships.role IN ('Owner', 'Moderator')) AND chat_messages.id = ?;";
        $msgs = $this->dbhr->preQuery($sql, [ $myid, $id ]);

        foreach ($msgs as $msg) {
            $this->dbhm->preExec("UPDATE chat_messages SET reviewrequired = 0, reviewedby = ? WHERE id = ?;", [
                $myid,
                $id
            ]);

            # Whitelist any URLs - they can't be indicative of spam.
            $this->whitelistURLs($msg['message']);

            # This is like a new message now, so alert them.
            $r = new ChatRoom($this->dbhr, $this->dbhm, $msg['chatid']);
            $r->updateMessageCounts();
            $u = User::get($this->dbhr, $this->dbhm, $msg['userid']);
            $r->pokeMembers();
            $r->notifyMembers($u->getName(), $msg['message'], $msg['userid']);
        }
    }

    public function reject($id) {
        $me = whoAmI($this->dbhr, $this->dbhm);
        $myid = $me ? $me->getId() : NULL;

        # We can only reject if we can see this message for review.
        $sql = "SELECT chat_messages.id, chat_messages.chatid FROM chat_messages INNER JOIN chat_rooms ON reviewrequired = 1 AND chat_rooms.id = chat_messages.chatid INNER JOIN memberships ON memberships.userid = (CASE WHEN chat_messages.userid = chat_rooms.user1 THEN chat_rooms.user2 ELSE chat_rooms.user1 END) AND memberships.groupid IN (SELECT groupid FROM memberships WHERE memberships.userid = ? AND memberships.role IN ('Owner', 'Moderator')) AND chat_messages.id = ?;";
        $msgs = $this->dbhr->preQuery($sql, [ $myid, $id ]);

        foreach ($msgs as $msg) {
            $this->dbhm->preExec("UPDATE chat_messages SET reviewrequired = 0, reviewedby = ?, reviewrejected = 1 WHERE id = ?;", [
                $myid,
                $id
            ]);

            $r = new ChatRoom($this->dbhr, $this->dbhm, $msg['chatid']);
            $r->updateMessageCounts();
        }
    }

    public function getReviewCount(User $me, $minage = NULL) {
        # For chats, we should see the messages which require review, and where we are a mod on one of the groups
        # that the recipient of the message (i.e. the chat member who isn't the one who sent it) is on.
        #
        # For some of these groups we may be set not to show messages - so we need to honour that.
        $show = [];
        $dontshow = [];

        $groupids = $me->getModeratorships();
        foreach ($groupids as $groupid) {
            if ($me->activeModForGroup($groupid)) {
                $show[] = $groupid;
            } else {
                $dontshow[] = $groupid;
            }
        }

        $showq = implode(',', $show);
        $dontshowq = implode(',', $dontshow);

        # We want the messages for review for any group where we are a mod and the recipient of the chat message is
        # a member.  Put a backstop time on it to avoid getting too many or
        # an inefficient query.
        # TODO This uses INSTR to check a json-encoded field.  In MySQL 5.7 we can do better.
        $mysqltime = date ("Y-m-d", strtotime("Midnight 31 days ago"));
        $minageq = $minage ? (" AND chat_messages.date <= '" . date ("Y-m-d H:i:s", strtotime("$minage hours ago")) . "' ") : '';
        $sql = "SELECT COUNT(DISTINCT chat_messages.id) AS count FROM chat_messages INNER JOIN chat_rooms ON reviewrequired = 1 AND chat_rooms.id = chat_messages.chatid INNER JOIN memberships ON memberships.userid = (CASE WHEN chat_messages.userid = chat_rooms.user1 THEN chat_rooms.user2 ELSE chat_rooms.user1 END) AND memberships.groupid IN ($showq) INNER JOIN groups ON memberships.groupid = groups.id AND groups.type = 'Freegle'  WHERE chat_messages.date > '$mysqltime' $minageq;";
        #error_log("Show SQL $sql");
        $showcount = count($show) > 0 ? $this->dbhr->preQuery($sql)[0]['count'] : 0;
        $dontshowcount = count($dontshow) > 0 ? $this->dbhr->preQuery("SELECT COUNT(DISTINCT chat_messages.id) AS count FROM chat_messages INNER JOIN chat_rooms ON reviewrequired = 1 AND chat_rooms.id = chat_messages.chatid INNER JOIN memberships ON memberships.userid = (CASE WHEN chat_messages.userid = chat_rooms.user1 THEN chat_rooms.user2 ELSE chat_rooms.user1 END) AND memberships.groupid IN ($dontshowq) INNER JOIN groups ON memberships.groupid = groups.id AND groups.type = 'Freegle' WHERE chat_messages.date > '$mysqltime' $minageq;")[0]['count'] : 0;

        return([
            'showgroups' => $showq,
            'dontshowgroups' => $dontshowq,
            'chatreview' => $showcount,
            'chatreviewother' => $dontshowcount
        ]);
    }

    public function hold($id) {
        $me = whoAmI($this->dbhr, $this->dbhm);
        $myid = $me ? $me->getId() : NULL;

        # We can only hold if we can see this message for review.
        $sql = "SELECT chat_messages.* FROM chat_messages INNER JOIN chat_rooms ON reviewrequired = 1 AND chat_rooms.id = chat_messages.chatid INNER JOIN memberships ON memberships.userid = (CASE WHEN chat_messages.userid = chat_rooms.user1 THEN chat_rooms.user2 ELSE chat_rooms.user1 END) AND memberships.groupid IN (SELECT groupid FROM memberships WHERE memberships.userid = ? AND memberships.role IN ('Owner', 'Moderator')) AND chat_messages.id = ?;";
        $msgs = $this->dbhr->preQuery($sql, [ $myid, $id ]);
        foreach ($msgs as $msg) {
            $this->dbhm->preExec("REPLACE INTO chat_messages_held (msgid, userid) VALUES (?, ?);", [
                $id,
                $me->getId()
            ]);
        }
    }

    public function release($id) {
        $me = whoAmI($this->dbhr, $this->dbhm);
        $myid = $me ? $me->getId() : NULL;

        # We can only release if we can see this message for review.
        $sql = "SELECT chat_messages.* FROM chat_messages INNER JOIN chat_rooms ON reviewrequired = 1 AND chat_rooms.id = chat_messages.chatid INNER JOIN memberships ON memberships.userid = (CASE WHEN chat_messages.userid = chat_rooms.user1 THEN chat_rooms.user2 ELSE chat_rooms.user1 END) AND memberships.groupid IN (SELECT groupid FROM memberships WHERE memberships.userid = ? AND memberships.role IN ('Owner', 'Moderator')) AND chat_messages.id = ?;";
        $msgs = $this->dbhr->preQuery($sql, [ $myid, $id ]);
        foreach ($msgs as $msg) {
            $this->dbhm->preExec("DELETE FROM chat_messages_held WHERE msgid = ?;", [
                $id
            ]);
        }
    }

    public function delete() {
        $rc = $this->dbhm->preExec("DELETE FROM chat_messages WHERE id = ?;", [$this->id]);
        return($rc);
    }
}