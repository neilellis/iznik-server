<?php
require_once(IZNIK_BASE . '/mailtemplates/verifymail.php');

function session() {
    global $dbhr, $dbhm;
    $me = NULL;

    $sessionLogout = function($dbhr, $dbhm) {
        $id = pres('id', $_SESSION);
        if ($id) {
            $s = new Session($dbhr, $dbhm);
            $s->destroy($id, NULL);
        }

        # Destroy the PHP session
        try {
            session_destroy();
            session_unset();
            session_start();
            session_regenerate_id(true);
        } catch (Exception $e) {
        }
    };

    $ret = [ 'ret' => 100, 'status' => 'Unknown verb' ];

    switch ($_REQUEST['type']) {
        case 'GET': {
            # Check if we're logged in
            if (pres('pushcreds', $_REQUEST)) {
                # This can log us in.  Don't want to use cached information when looking at our own session.
                $me = whoAmI($dbhm, $dbhm);
            }

            if (pres('id', $_SESSION)) {
                $components = presdef('components', $_REQUEST, ['all']);
                if ($components === ['all']) {
                    // Get all
                    $components = NULL;
                }

                $ret = [ 'ret' => 0, 'status' => 'Success' ];

                if (!$components || in_array('me', $components)) {
                    # Don't want to use cached information when looking at our own session.
                    $me = whoAmI($dbhm, $dbhm);

                    $ret['me'] = $me->getPublic();

                    # Don't need to return this, and it might be large.
                    $ret['me']['messagehistory'] = NULL;
                }

                $ret['persistent'] = presdef('persistent', $_SESSION, NULL);

                if (!$components || in_array('notifications', $components)) {
                    $n = new PushNotifications($dbhr, $dbhm);
                    $ret['me']['notifications'] = [
                        'push' => $n->get($ret['me']['id'])
                    ];
                }

                if (MODTOOLS) {
                    if (!$components || in_array('allconfigs', $components)) {
                        $me = $me ? $me : whoAmI($dbhm, $dbhm);
                        $ret['configs'] = $me->getConfigs(TRUE);
                    } else if (in_array('configs', $components)) {
                        $me = $me ? $me : whoAmI($dbhm, $dbhm);
                        $ret['configs'] = $me->getConfigs(FALSE);
                    }
                }

                if (!$components || in_array('emails', $components)) {
                    $me = $me ? $me : whoAmI($dbhm, $dbhm);
                    $ret['emails'] = $me->getEmails();
                }

                if (!$components || in_array('phone', $components)) {
                    $me = $me ? $me : whoAmI($dbhm, $dbhm);
                    $ret['me']['phone'] = $me->getPhone();
                }

                if (!$components || in_array('aboutme', $components)) {
                    $me = $me ? $me : whoAmI($dbhm, $dbhm);
                    $ret['me']['aboutme'] = $me->getAboutMe();
                }

                if (!$components || in_array('newsfeed', $components)) {
                    # Newsfeed count.  We return this in the session to avoid getting it on each page transition
                    # in the client.
                    $n = new Newsfeed($dbhr, $dbhm);
                    $me = $me ? $me : whoAmI($dbhm, $dbhm);
                    $ret['newsfeedcount'] = $n->getUnseen($me->getId());
                }

                if (!$components || in_array('logins', $components)) {
                    $me = $me ? $me : whoAmI($dbhm, $dbhm);
                    $ret['logins'] = $me->getLogins(FALSE);
                }

                if (!$components || in_array('groups', $components) || in_array('work', $components)) {
                    # Get groups including work when we're on ModTools; don't need that on the user site.
                    $u = new User($dbhr, $dbhm);
                    $ret['groups'] = $u->getMemberships(FALSE, NULL, MODTOOLS, TRUE, $_SESSION['id']);

                    $gids = [];

                    foreach ($ret['groups'] as &$group) {
                        $gids[] = $group['id'];

                        # Remove large attributes we don't need in session.
                        unset($group['welcomemail']);
                        unset($group['description']);
                        unset($group['settings']['chaseups']['idle']);
                        unset($group['settings']['branding']);
                    }

                    # We should always return complete groups objects because they are stored in the client session.
                    #
                    # If we have many groups this can generate many DB calls, so quicker to prefetch for Twitter and
                    # Facebook, even though that makes the code hackier.
                    $facebooks = GroupFacebook::listForGroups($dbhr, $dbhm, $gids);
                    $twitters = [];

                    if (count($gids) > 0) {
                        # We don't want to show any ones which aren't properly linked (yet), i.e. name is null.
                        $tws = $dbhr->preQuery("SELECT * FROM groups_twitter WHERE groupid IN (" . implode(',', $gids) . ") AND name IS NOT NULL;");
                        foreach ($tws as $tw) {
                            $twitters[$tw['groupid']] = $tw;
                        }
                    }

                    foreach ($ret['groups'] as &$group) {
                        if ($group['role'] == User::ROLE_MODERATOR || $group['role'] == User::ROLE_OWNER) {
                            # Return info on Twitter status.  This isn't secret info - we don't put anything confidential
                            # in here - but it's of no interest to members so there's no point delaying them by
                            # fetching it.
                            #
                            # Similar code in group.php.
                            if (array_key_exists($group['id'], $twitters)) {
                                $t = new Twitter($dbhr, $dbhm, $group['id'], $twitters[$group['id']]);
                                $atts = $t->getPublic();
                                unset($atts['token']);
                                unset($atts['secret']);
                                $atts['authdate'] = ISODate($atts['authdate']);
                                $group['twitter'] = $atts;
                            }

                            # Ditto Facebook.
                            if (array_key_exists($group['id'], $facebooks)) {
                                $group['facebook'] = [];

                                foreach ($facebooks[$group['id']] as $atts) {
                                    $group['facebook'][] = $atts;
                                }
                            }
                        }
                    }

                    if (MODTOOLS) {
                        if (!$components || in_array('work', $components)) {
                            # Tell them what mod work there is.  Similar code in Notifications.
                            $ret['work'] = [];
                            $national = FALSE;

                            if (!$me) {
                                # When getting work we want to avoid instantiating the full User object.  But
                                # we need the memberships.  So work around that.  Bit hacky but saves ops in a
                                # perf critical path.
                                $me = new User($dbhr, $dbhm);
                                $me->cacheMemberships($_SESSION['id']);
                                $perms = $dbhr->preQuery("SELECT permissions FROM users WHERE id = ?;", [
                                    $_SESSION['id']
                                ]);

                                foreach ($perms as $perm) {
                                    $national = stripos($perm['permissions'], User::PERM_NATIONAL_VOLUNTEERS) !== FALSE;
                                }
                            } else {
                                $national = $me->hasPermission(User::PERM_NATIONAL_VOLUNTEERS);
                            }

                            if ($national) {
                                $v = new Volunteering($dbhr, $dbhm);
                                $ret['work']['pendingvolunteering'] = $v->systemWideCount();
                            }


                            $s = new Spam($dbhr, $dbhm);
                            $spamcounts = $s->collectionCounts();
                            $ret['work']['spammerpendingadd'] = $spamcounts[Spam::TYPE_PENDING_ADD];
                            $ret['work']['spammerpendingremove'] = $spamcounts[Spam::TYPE_PENDING_REMOVE];

                            # Show social actions from last 4 days.
                            $ctx = NULL;
                            $starttime = date("Y-m-d H:i:s", strtotime("midnight 4 days ago"));
                            $f = new GroupFacebook($dbhr, $dbhm);
                            $ret['work']['socialactions'] = count($f->listSocialActions($ctx, $starttime));

                            $c = new ChatMessage($dbhr, $dbhm);

                            $ret['work'] = array_merge($ret['work'], $c->getReviewCount($me));

                            $s = new Story($dbhr, $dbhm);
                            $ret['work']['stories'] = $s->getReviewCount(FALSE);
                            $ret['work']['newsletterstories'] = $me->hasPermission(User::PERM_NEWSLETTER) ? $s->getReviewCount(TRUE) : 0;
                        }

                        foreach ($ret['groups'] as &$group) {
                            if (pres('work', $group)) {
                                foreach ($group['work'] as $key => $work) {
                                    if (pres('work', $ret) && pres($key, $ret['work'])) {
                                        $ret['work'][$key] += $work;
                                    } else {
                                        $ret['work'][$key] = $work;
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $ret = array('ret' => 1, 'status' => 'Not logged in');
            }

            break;
        }

        case 'POST': {
            # Don't want to use cached information when looking at our own session.
            $me = whoAmI($dbhm, $dbhm);

            # Login
            $fblogin = array_key_exists('fblogin', $_REQUEST) ? filter_var($_REQUEST['fblogin'], FILTER_VALIDATE_BOOLEAN) : FALSE;
            $fbaccesstoken = presdef('fbaccesstoken', $_REQUEST, NULL);
            $googlelogin = array_key_exists('googlelogin', $_REQUEST) ? filter_var($_REQUEST['googlelogin'], FILTER_VALIDATE_BOOLEAN) : FALSE;
            $yahoologin = array_key_exists('yahoologin', $_REQUEST) ? filter_var($_REQUEST['yahoologin'], FILTER_VALIDATE_BOOLEAN) : FALSE;
            $googleauthcode = array_key_exists('googleauthcode', $_REQUEST) ? $_REQUEST['googleauthcode'] : NULL;
            $mobile = array_key_exists('mobile', $_REQUEST) ? filter_var($_REQUEST['mobile'], FILTER_VALIDATE_BOOLEAN) : FALSE;
            $email = array_key_exists('email', $_REQUEST) ? $_REQUEST['email'] : NULL;
            $password = array_key_exists('password', $_REQUEST) ? $_REQUEST['password'] : NULL;
            $returnto = array_key_exists('returnto', $_REQUEST) ? $_REQUEST['returnto'] : NULL;
            $action = presdef('action', $_REQUEST, NULL);

            $id = NULL;
            $user = User::get($dbhr, $dbhm);
            $f = NULL;
            $ret = array('ret' => 1, 'status' => 'Invalid login details');

            if ($fblogin) {
                # We've been asked to log in via Facebook.
                $f = new Facebook($dbhr, $dbhm);
                list ($session, $ret) = $f->login($fbaccesstoken);
                /** @var Session $session */
                $id = $session ? $session->getUserId() : NULL;
            } else if ($yahoologin) {
                # Yahoo.
                $y = Yahoo::getInstance($dbhr, $dbhm);
                list ($session, $ret) = $y->login($returnto);
                /** @var Session $session */
                $id = $session ? $session->getUserId() : NULL;
            } else if ($googlelogin) {
                # Google
                $g = new Google($dbhr, $dbhm, $mobile);
                list ($session, $ret) = $g->login($googleauthcode);
                /** @var Session $session */
                $id = $session ? $session->getUserId() : NULL;
            } else if ($action) {
                switch ($action) {
                    case 'LostPassword': {
                        $id = $user->findByEmail($email);
                        $ret = [ 'ret' => 2, "We don't know that email address" ];
                        
                        if ($id) {
                            $u = User::get($dbhr, $dbhm, $id);
                            $u->forgotPassword($email);
                            $ret = [ 'ret' => 0, 'status' => "Success" ];
                        }    
                        
                        break;
                    }

                    case 'Forget': {
                        $ret = array('ret' => 1, 'status' => 'Not logged in');

                        if ($me) {
                            # We don't allow mods/owners to do this, as they might do it by accident.
                            $ret = array('ret' => 2, 'status' => 'Please demote yourself to a member first');

                            if (!$me->isModerator()) {
                                # We don't allow spammers to do this.
                                $ret = array('ret' => 3, 'status' => 'We can\'t do this.');

                                $s = new Spam($dbhr, $dbhm);

                                if (!$s->isSpammer($me->getEmailPreferred())) {
                                    $me->forget();

                                    # Log out.
                                    $sessionLogout($dbhr, $dbhm);
                                    $ret = [ 'ret' => 0, 'status' => "Success" ];
                                }
                            }
                        }
                        break;
                    }
                }
            }
            else if ($password && $email) {
                # Native login via username and password
                $ret = array('ret' => 2, 'status' => "We don't know that email address.  If you're new, please Sign Up.");
                $possid = $user->findByEmail($email);
                if ($possid) {
                    $ret = array('ret' => 3, 'status' => "The password is wrong.  Maybe you've forgotten it?");
                    $u = User::get($dbhr, $dbhm, $possid);

                    # If we are currently logged in as an admin, then we can force a log in as anyone else.  This is
                    # very useful for debugging.
                    $force = $me && $me->isAdmin();

                    if ($u->login($password, $force)) {
                        $ret = array('ret' => 0, 'status' => 'Success');
                        $id = $possid;

                        # We have publish permissions for users who login via our platform.
                        $u->setPrivate('publishconsent', 1);
                    }
                }
            }

            if ($id) {
                # Return some more useful info.
                $u = User::get($dbhr, $dbhm, $id);
                $ret['user'] = $u->getPublic();
                $ret['persistent'] = presdef('persistent', $_SESSION, NULL);
            }

            break;
        }

        case 'PATCH': {
            # Don't want to use cached information when looking at our own session.
            $me = whoAmI($dbhm, $dbhm);

            if (!$me) {
                $ret = ['ret' => 1, 'status' => 'Not logged in'];
            } else {
                $fullname = presdef('displayname', $_REQUEST, NULL);
                $firstname = presdef('firstname', $_REQUEST, NULL);
                $lastname = presdef('lastname', $_REQUEST, NULL);
                $password = presdef('password', $_REQUEST, NULL);
                $key = presdef('key', $_REQUEST, NULL);

                if ($firstname) {
                    $me->setPrivate('firstname', $firstname);
                }
                if ($lastname) {
                    $me->setPrivate('lastname', $lastname);
                }
                if ($fullname) {
                    # Fullname is what we set from the client.  Zap the first/last names so that people who change
                    # their name for privacy reasons are respected.
                    $me->setPrivate('fullname', $fullname);
                    $me->setPrivate('firstname', NULL);
                    $me->setPrivate('lastname', NULL);
                }

                $settings = presdef('settings', $_REQUEST, NULL);
                if ($settings) {
                    $me->setPrivate('settings', json_encode($settings));

                    if (pres('mylocation', $settings)) {
                        # Save this off as the last known location.
                        $me->setPrivate('lastlocation', $settings['mylocation']['id']);
                    }
                }

                $notifs = presdef('notifications', $_REQUEST, NULL);
                if ($notifs) {
                    $n = new PushNotifications($dbhr, $dbhm);
                    $push = presdef('push', $notifs, NULL);
                    if ($push) {
                        switch ($push['type']) {
                            case PushNotifications::PUSH_GOOGLE:
                            case PushNotifications::PUSH_FIREFOX:
                            case PushNotifications::PUSH_ANDROID:
                            case PushNotifications::PUSH_FCM_ANDROID:
                            case PushNotifications::PUSH_FCM_IOS:
                            case PushNotifications::PUSH_IOS:
                                $n->add($me->getId(), $push['type'], $push['subscription']);
                                break;
                        }
                    }
                }

                $ret = ['ret' => 0, 'status' => 'Success'];

                $email = presdef('email', $_REQUEST, NULL);
                if ($email) {
                    if (!$me->verifyEmail($email)) {
                        $ret = ['ret' => 10, 'status' => "We've sent a verification mail; please check your mailbox." ];
                    }
                }

                if (array_key_exists('phone', $_REQUEST)) {
                    $phone = $_REQUEST['phone'];
                    if ($phone) {
                        $me->addPhone($phone);
                    } else {
                        $me->removePhone();
                    }
                }

                if ($key) {
                    if (!$me->confirmEmail($key)) {
                        $ret = ['ret' => 11, 'status' => 'Confirmation failed'];
                    }
                }

                if ($password) {
                    $me->addLogin(User::LOGIN_NATIVE, $me->getId(), $password);
                }

                if (array_key_exists('onholidaytill', $_REQUEST)) {
                    $me->setPrivate('onholidaytill', $_REQUEST['onholidaytill']);
                }

                if (array_key_exists('relevantallowed', $_REQUEST)) {
                    $me->setPrivate('relevantallowed', $_REQUEST['relevantallowed']);
                }

                if (array_key_exists('newslettersallowed', $_REQUEST)) {
                    $me->setPrivate('newslettersallowed', $_REQUEST['newslettersallowed']);
                }

                if (array_key_exists('aboutme', $_REQUEST)) {
                    $me->setAboutMe($_REQUEST['aboutme']);

                    if (strlen($_REQUEST['aboutme']) > 5) {
                        # Newsworthy.  But people might edit them a lot for typos, so look for a recent other
                        # one and update that before adding a new one.
                        $n = new Newsfeed($dbhr, $dbhm);
                        $nid = $n->findRecent($me->getId(), Newsfeed::TYPE_ABOUT_ME);

                        if ($nid) {
                            # Found a recent one - update it.
                            $n = new Newsfeed($dbhr, $dbhm, $nid);
                            $n->setPrivate('message', $_REQUEST['aboutme']);
                        } else {
                            # No recent ones - add a new item
                            $n->create(Newsfeed::TYPE_ABOUT_ME, $me->getId(), $_REQUEST['aboutme']);
                        }
                    }
                }

                Session::clearSessionCache();
            }
            break;
        }

        case 'DELETE': {
            # Logout.  Kill all sessions for this user.
            $ret = array('ret' => 0, 'status' => 'Success');
            $sessionLogout($dbhr, $dbhm);
            break;
        }
    }

    return($ret);
}
