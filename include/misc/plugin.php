<?php

require_once(IZNIK_BASE . '/include/utils.php');

class Plugin {
    /** @var  $dbhr LoggedPDO */
    private $dbhr;

    /** @var  $dbhm LoggedPDO */
    private $dbhm;

    function __construct($dbhr, $dbhm, $id = NULL)
    {
        $this->dbhr = $dbhr;
        $this->dbhm = $dbhm;
    }

    public function add($groupid, $data) {
        $sql = "INSERT INTO plugin (groupid, data) VALUES (?,?);";
        $this->dbhm->preExec($sql, [
            $groupid,
            json_encode($data)
        ]);
    }

    public function get($groupid) {
        # Put a limit on to avoid swamping a particular user with work.  They'll pick it up again later.
        $sql = "SELECT * FROM plugin WHERE groupid = ? LIMIT 100;";
        return($this->dbhr->preQuery($sql, [ $groupid ]));
    }

    public function delete($id) {
        $sql = "DELETE FROM plugin WHERE id = ?;";
        return($this->dbhm->preExec($sql, [ $id ]));
    }
}