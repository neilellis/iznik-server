<?php
function chatrooms() {
    global $dbhr, $dbhm;

    $me = whoAmI($dbhr, $dbhm);
    $myid = $me ? $me->getId() : $me;

    $id = intval(presdef('id', $_REQUEST, NULL));
    $userid = intval(presdef('userid', $_REQUEST, NULL));
    $r = new ChatRoom($dbhr, $dbhm, $id);
    $chattypes = presdef('chattypes', $_REQUEST, [ ChatRoom::TYPE_USER2USER ]);
    $chattype = presdef('chattype', $_REQUEST, ChatRoom::TYPE_USER2USER);
    $groupid = intval(presdef('groupid', $_REQUEST, NULL));
    $search = presdef('search', $_REQUEST, NULL);
    $summary = array_key_exists('summary', $_REQUEST) ? filter_var($_REQUEST['summary'], FILTER_VALIDATE_BOOLEAN) : FALSE;

    $ret = [ 'ret' => 100, 'status' => 'Unknown verb' ];

    switch ($_REQUEST['type']) {
        case 'GET': {
            if ($id) {
                $ret = [ 'ret' => 0, 'status' => 'Success' ];
                $ret['chatroom'] = NULL;

                if ($r->canSee($myid)) {
                    $ret['chatroom'] = $r->getPublic();
                    $ret['chatroom']['unseen'] = $r->unseenCountForUser($myid);
                    $ret['chatroom']['lastmsgseen'] = $r->lastSeenForUser($myid);
                }
            } else {
                $ctx = NULL;
                $ret = [ 'ret' => 1, 'status' => 'Not logged in' ];

                if ($me) {
                    $ret = [ 'ret' => 0, 'status' => 'Success' ];

                    $rooms = $r->listForUser($myid, $chattypes, $search, MODTOOLS);
                    $ret['chatrooms'] = [];

                    if ($rooms) {
                        # Get all the attributes we need in a single query for performance reasons.
                        $ret['chatrooms'] = $r->fetchRooms($rooms, $myid, $summary);
                    }
                }
            }
            break;
        }

        case 'PUT': {
            # Create a conversation.
            $ret = ['ret' => 1, 'status' => 'Not logged in'];

            if ($me) {
                switch ($chattype) {
                    case ChatRoom::TYPE_USER2USER:
                        if ($userid) {
                            $id = $r->createConversation($myid, $userid);
                        }
                        break;
                    case ChatRoom::TYPE_USER2MOD:
                        $id = $r->createUser2Mod($myid, $groupid);
                        break;
                }

                $ret = ['ret' => 3, 'status' => 'Create failed'];
                if ($id) {
                    $ret = ['ret' => 0, 'status' => 'Success', 'id' => $id];
                }
            }
            break;
        }

        case 'POST': {
            # Update our presence and get the current roster.
            $ret = [ 'ret' => 1, 'status' => 'Not logged in' ];
            $action = presdef('action', $_REQUEST, NULL);

            if ($me) {
                if ($action == 'AllSeen') {
                    $chatids = $r->listForUser($myid);
                    $count = 0;

                    if ($chatids) {
                        foreach ($chatids as $chatid) {
                            $r = new ChatRoom($dbhr, $dbhm, $chatid);

                            $unseen = $r->unseenCountForUser($myid);
                            $r->upToDate($myid);
                            $count += $unseen;
                        }
                    }

                    $ret = ['ret' => 0, 'status' => 'Success', 'count' => $count];
                } else if ($action == 'Nudge') {
                    $id = $r->nudge();
                    $ret = ['ret' => 0, 'status' => 'Success', 'id' => $id];
                } else if ($id) {
                    # Single roster update.
                    $ret = ['ret' => 2, 'status' => "$id Not visible to you"];

                    if ($r->canSee($myid)) {
                        $ret = ['ret' => 0, 'status' => 'Success'];
                        $lastmsgseen = presdef('lastmsgseen', $_REQUEST, NULL);
                        $status = presdef('status', $_REQUEST, ChatRoom::STATUS_ONLINE);
                        $r->updateRoster($myid, $lastmsgseen, $status);

                        $ret['roster'] = $r->getRoster();
                        $ret['unseen'] = $r->unseenCountForUser($myid);
                        $ret['nolog'] = TRUE;
                    }
                }
            }
        }
    }

    return($ret);
}
