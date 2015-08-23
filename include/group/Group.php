<?php

require_once(BASE_DIR . '/include/utils.php');
require_once(BASE_DIR . '/include/Entity.php');

class Group extends Entity
{
    /** @var  $dbhm LoggedPDO */
    private $publicatts = array('id', 'nameshort', 'namefull', 'nameabbr', 'settings');

    const GROUP_REUSE = 'Reuse';
    const GROUP_FREEGLE = 'Freegle';

    function __construct(LoggedPDO $dbhr, LoggedPDO $dbhm, $id = NULL)
    {
        $this->fetch($dbhr, $dbhm, $id, 'groups', 'group', $this->publicatts);
    }

    public function create($shortname, $type) {
        $rc = $this->dbhm->preExec("INSERT INTO groups (nameshort, type) VALUES (?, ?)", [$shortname, $type]);
        $id = $this->dbhm->lastInsertId();
        error_log("Last insert id $id");

        if ($rc) {
            $this->fetch($this->dbhr, $this->dbhm, $id, 'groups', 'group', $this->publicatts);
        }
        return($rc);
    }
}