<?php

/**
 * Class pocketlistsItemModel
 */
class pocketlistsItemModel extends waModel
{
    const PRIORITY_NORM       = 0;
    const PRIORITY_GREEN      = 1;
    const PRIORITY_YELLOW     = 2;
    const PRIORITY_RED        = 3;
    const PRIORITY_BLACK      = 4;
    const PRIORITY_BURNINHELL = 5;

    /**
     * @var string
     */
    protected $table = 'pocketlists_item';

    /**
     * @return array
     */
    public static function getPriorities()
    {
        return [
            'norm'       => self::PRIORITY_NORM,
            'green'      => self::PRIORITY_GREEN,
            'yellow'     => self::PRIORITY_YELLOW,
            'red'        => self::PRIORITY_RED,
            'black'      => self::PRIORITY_BLACK,
            'burninhell' => self::PRIORITY_BURNINHELL,
        ];
    }

    /**
     * @param bool $contact_id
     * @param bool $date_range
     *
     * @return array
     */
    public function getCompletedItems($contact_id = false, $date_range = false)
    {
        return $this->getLogbookItems($contact_id, $date_range);
    }


    /**
     * @param bool $contact_id
     * @param bool $date_range
     * @param bool $completed
     * @param int  $start
     * @param int  $limit
     *
     * @return array
     */
    public function getLogbookItems(
        $contact_id = false,
        $date_range = false,
        $completed = false,
        $start = 0,
        $limit = 50
    ) {
        $by_user = '';
        if ($contact_id) {
            $by_user = 'AND i.complete_contact_id = i:contact_id';
        }
        $by_date_range = '';
        if ($date_range && is_array($date_range)) {
            if (!empty($date_range['after'])) {
                $by_date_range = '  AND i.complete_datetime > s:date_after';
            }
            if (!empty($date_range['before'])) {
                $by_date_range .= '  AND i.complete_datetime < s:date_before';
            }
        }

        $only_completed = '';
        if ($completed) {
            $only_completed = " AND i.status > 0";
        }

        $lists = pocketlistsRBAC::getAccessListForContact();
        $list_sql = $lists ? "AND (l.id IN (i:list_ids) OR (l.id IS NULL AND i.contact_id = i:contact_id))" : "AND (l.id IS NULL AND i.contact_id = i:contact_id)";
        $sql = $this->getQuery().
            "LEFT JOIN (select i2.name, l2.*
                          from pocketlists_list l2
                                 JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) l ON l.id = i.list_id
                WHERE
                  i.key_list_id IS NULL
                  {$list_sql}
                  {$only_completed}
                  {$by_user}
                  {$by_date_range}
                ORDER BY i.complete_datetime DESC
                LIMIT {$start}, {$limit}";

        $items = $this->query(
            $sql,
            [
                'contact_id'  => wa()->getUser()->getId(),
                'list_ids'    => $lists,
                'date_after'  => !empty($date_range['after']) ? $date_range['after'] : '',
                'date_before' => !empty($date_range['before']) ? $date_range['before'] : '',
                'start'       => $start,
                'limit'       => $limit,
            ]
        )->fetchAll();

        return $items;
    }

    /**
     * @param int   $contact_id
     * @param array $dateBounds
     *
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getToDo($contact_id = 0, $dateBounds = [])
    {
        $dateBounds = $dateBounds ?: [];

        if (!is_array($dateBounds)) {
            $dateBounds = [$dateBounds];
        }

        if (!$contact_id) {
            $contact_id = wa()->getUser()->getId();
        }

        $items = $this->fetchTodo($contact_id, $dateBounds, $calc_priority = []);

        return $items;
    }

    /**
     * @param $contact_id
     * @param $dateBounds
     *
     * @return array
     *
     * @throws waDbException
     * @throws waException
     */
    public function fetchTodo($contact_id, $dateBounds = [], array $calc_priority = [])
    {
        // get to-do items only from accessed pockets
        $lists = pocketlistsRBAC::getAccessListForContact($contact_id);

        $sqlParts = $this->getTodoSql($contact_id, $dateBounds, $lists, $calc_priority);

        $or_sql = implode("\n OR ", $sqlParts['or']);
        $and_sql = implode("\n AND ", $sqlParts['and']);
        $join_sql = implode("\n LEFT JOIN ", $sqlParts['join']);
        $select_sql = implode(",\n", $sqlParts['select']);

        $sql = "SELECT
                  {$select_sql}
                FROM {$this->table} i
                {$join_sql}
                WHERE
                  ({$or_sql}) 
                  AND 
                  {$and_sql}
                GROUP BY i.id
                ORDER BY
                  i.status,
                  (i.complete_datetime IS NULL), i.complete_datetime DESC,
                  i.id DESC";

        $items = $this->query(
            $sql,
            [
                'list_ids'      => $lists,
                'contact_id'    => $contact_id,
                'date'          => isset($dateBounds[0]) ? $dateBounds[0] : '',
                'date2'         => isset($dateBounds[1]) ? $dateBounds[1] : '',
                'calc_priority' => $calc_priority,
            ]
        )->fetchAll();

        return $items;
    }

    /**
     * @param $contact_id
     * @param $date
     *
     * @return array
     *
     * @throws waDbException
     * @throws waException
     */
    public function countTodo($contact_id, $date = [], array $calc_priority = [])
    {
        // get to-do items only from accessed pockets
        $lists = pocketlistsRBAC::getAccessListForContact($contact_id);

        $sqlParts = $this->getTodoSql($contact_id, $date, $lists, $calc_priority);

        $sqlParts['select'] = ['count(i.id)'];
        $sqlParts['and'][] = 'i.status = 0';

        $or_sql = implode("\n OR ", $sqlParts['or']);
        $and_sql = implode("\n AND ", $sqlParts['and']);
        $join_sql = implode("\n LEFT JOIN ", $sqlParts['join']);
        $select_sql = implode(",\n", $sqlParts['select']);

        $sql = "SELECT
                  {$select_sql}
                FROM {$this->table} i
                {$join_sql}
                WHERE
                  ({$or_sql}) 
                  AND 
                  {$and_sql}";

        $items = $this->query(
            $sql,
            [
                'list_ids'      => $lists,
                'contact_id'    => $contact_id,
                'date'          => $date,
                'calc_priority' => $calc_priority,
            ]
        )->fetchField();

        return $items;
    }

    /**
     * @param       $contact_id
     * @param       $dateBounds
     * @param       $lists
     * @param array $calc_priority
     *
     * @return array
     * @throws waDbException
     * @throws waException
     */
    protected function getTodoSql($contact_id, array $dateBounds, $lists, array $calc_priority = [])
    {
        $sqlParts = [
            'select' => [],
            'join'   => [],
            'and'    => [],
            'or'     => [],
        ];

        $sqlParts['select'] = [
            'i.*',
            'IF(uf.contact_id, 1, 0) favorite',
            'l.color list_color',
        ];

        $sqlParts['join'] = [
            '',
            'pocketlists_user_favorites uf ON uf.contact_id = i:contact_id AND uf.item_id = i.id',
            '(select i2.name, l2.*
                  from pocketlists_list l2
                         JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) l ON l.id = i.list_id AND l.archived = 0',
        ];

        $sqlParts['and'] = [
            sprintf('(%s OR l.id IS NULL)', pocketlistsRBAC::filterListAccess($lists, $contact_id)),
            // get to-do items only from accмфп essed pockets
        ];

        $sqlParts['or'] = [
            '(i.list_id IS NULL AND i.key_list_id IS NULL AND i.contact_id = i:contact_id) /* My to-dos to self */',
            '(i.key_list_id IS NULL AND i.assigned_contact_id = i:contact_id) /* To-dos assigned to me by other users */',
        ];

        if ($dateBounds) {
            if (isset($dateBounds[1])) {
                $sqlParts['and'][] = '((i.status = 0 AND (i.due_date BETWEEN s:date AND s:date2 OR DATE(i.due_datetime) BETWEEN s:date AND s:date2)) OR (i.status > 0 AND DATE(i.complete_datetime) BETWEEN s:date AND s:date2)) /* with due date or completed this day */';
            } elseif (isset($dateBounds[0])) {
                $sqlParts['and'][] = '((i.status = 0 AND (i.due_date = s:date OR DATE(i.due_datetime) = s:date)) OR (i.status > 0 AND DATE(i.complete_datetime) = s:date)) /* with due date or completed this day */';
            }
        }

        $us = new pocketlistsUserSettings($contact_id);

        switch ($us->myToDosCreatedByMe()) {
            case pocketlistsUserSettings::MY_TO_DOS_CREATED_BY_ME_IN_SHARED_ANY_LIST:
                $sqlParts['or'][] = '(i.list_id IS NOT NULL AND i.key_list_id IS NULL AND i.contact_id = i:contact_id) /* To-dos created by me in shared lists in just any list */';
                break;

            case pocketlistsUserSettings::MY_TO_DOS_CREATED_BY_ME_IN_SHARED_FAVORITE_LISTS:
                $sqlParts['or'][] = '(i.list_id IS NOT NULL AND i.key_list_id IS NULL AND i.contact_id = i:contact_id AND uf2.contact_id = i:contact_id) /* To-dos created BY other users IN shared lists only in lists which I marked as favorite*/';
                break;
        }

        switch ($us->myToDosCreatedByOthers()) {
            case pocketlistsUserSettings::MY_TO_DOS_CREATED_BY_OTHER_IN_SHARED_LISTS_FAVORITE_LISTS:
                $sqlParts['or'][] = '(i.list_id IS NOT NULL AND i.key_list_id IS NULL AND i.contact_id <> i:contact_id AND uf2.contact_id = i:contact_id) /* To-dos created BY other users IN shared lists only in lists which I marked as favorite*/';
                break;

            case pocketlistsUserSettings::MY_TO_DOS_CREATED_BY_OTHER_IN_SHARED_LISTS_GREEN_YELLOW_RED_ALL_LISTS:
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                $day_after_tomorrow = date("Y-m-d", strtotime('+2 day'));
                $sqlParts['or'][] = sprintf(
                    "(i.list_id IS NOT NULL AND i.key_list_id IS NULL AND i.contact_id <> i:contact_id AND (i.due_date <= '%s' OR i.due_datetime < '%s' OR i.priority IN (%s))) /* To-dos created BY other users IN shared lists all Green, Yellow, and Red to-dos from all lists*/",
                    $tomorrow,
                    $day_after_tomorrow,
                    implode(
                        ',',
                        [
                            self::PRIORITY_GREEN,
                            self::PRIORITY_YELLOW,
                            self::PRIORITY_RED,
                            self::PRIORITY_BLACK,
                            self::PRIORITY_BURNINHELL,
                        ]
                    )
                );
                break;
        }

        if ($us->myToDosCreatedByOthers() == pocketlistsUserSettings::MY_TO_DOS_CREATED_BY_OTHER_IN_SHARED_LISTS_FAVORITE_LISTS
            || $us->myToDosCreatedByMe() == pocketlistsUserSettings::MY_TO_DOS_CREATED_BY_ME_IN_SHARED_FAVORITE_LISTS) {
            $sqlParts['join'][] = 'pocketlists_user_favorites uf2 ON uf2.contact_id = i:contact_id AND uf2.item_id = l.key_item_id';

            $sqlParts['select'][] = 'IF(uf2.contact_id, 1, 0) favorite_list';
        }

        $sqlParts['and'][] = '(i.assigned_contact_id = i:contact_id OR i.assigned_contact_id IS NULL)';

        if ($calc_priority) {
            $sqlParts['and'][] = '(i.calc_priority in (i:calc_priority))';
        }

        return $sqlParts;
    }

    /**
     * @param bool $contact_id
     *
     * @return array
     */
    public function getFavoritesCount($contact_id = false)
    {
        if (!$contact_id) {
            $contact_id = wa()->getUser()->getId();
        }

        $lists = pocketlistsRBAC::getAccessListForContact($contact_id);
        $lists_sql = $lists ? " AND (l.id IN (i:list_ids) OR l.id IS NULL) /* ONLY items from accessed pockets or NULL-list items */" : " AND l.id IS NULL /* ONLY items from accessed pockets or NULL-list items */";
        $sql = "SELECT
                  SUM(i.status > 0) done,
                  SUM(i.status = 0) undone
                FROM {$this->table} i
                LEFT JOIN pocketlists_list l ON l.id = i.key_list_id AND l.archived = 0 /* ONLY not archived items */
                LEFT JOIN pocketlists_user_favorites uf ON uf.contact_id = i:contact_id AND uf.item_id = i.id
                WHERE
                  uf.item_id IS NOT NULL
                  {$lists_sql}";

        return $this->query(
            $sql,
            [
                'list_ids'   => $lists,
                'contact_id' => $contact_id,
            ]
        )->fetch();
    }

    /**
     * @param bool $contact_id
     * @param bool $date
     *
     * @return array
     */
    public function getFavorites($contact_id = false, $date = false, $date2 = false)
    {
        if (!$contact_id) {
            $contact_id = wa()->getUser()->getId();
        }
        // get to-do items only from accessed pockets
        $lists = pocketlistsRBAC::getAccessListForContact($contact_id);
        $lists_sql = $lists ? " AND (l.id IN (i:list_ids) OR l.id IS NULL) /* ONLY items from accessed pockets or NULL-list items */" : " AND l.id IS NULL /* ONLY items from NULL-list items */";

        $due_date_sql = '';

        if ($date) {
            $due_date_sql = 'AND ((i.status = 0 AND (i.due_date = s:date OR DATE(i.due_datetime) = s:date)) OR (i.status > 0 AND DATE(i.complete_datetime) = s:date)) /* with due date or completed this day */';
        }

        if ($date2) {
            $due_date_sql = 'AND ((i.status = 0 AND (i.due_date BETWEEN s:date AND s:date2 OR DATE(i.due_datetime) BETWEEN s:date AND s:date2)) OR (i.status > 0 AND DATE(i.complete_datetime) BETWEEN s:date AND s:date2)) /* with due date or completed this day */';
        }

        $sql = "SELECT
                  i.id id,
                  i.parent_id parent_id,
                  i.has_children has_children,
                  i.name name,
                  i.note note,
                  i.status status,
                  i.priority priority,
                  i.contact_id contact_id,
                  i.create_datetime create_datetime,
                  i.due_date due_date,
                  i.due_datetime due_datetime,
                  i.complete_datetime complete_datetime,
                  i.complete_contact_id complete_contact_id,
                  i.assigned_contact_id assigned_contact_id,
                  i.key_list_id key_list_id,
                  l.id list_id,
                  l.icon list_icon,
                  l.color list_color,
                  l.name list_name,
                  IF(uf.contact_id, 1, 0) favorite
                FROM {$this->table} i
                LEFT JOIN (select i2.name, l2.*
                          from pocketlists_list l2
                                 JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) l ON l.id = i.list_id AND l.archived = 0 /* ONLY not archived items */
                LEFT JOIN pocketlists_user_favorites uf ON uf.contact_id = i:contact_id AND uf.item_id = i.id
                WHERE
                uf.item_id IS NOT NULL
                {$due_date_sql}
                {$lists_sql}
                ORDER BY
                  i.status,
                  (i.complete_datetime IS NULL), i.complete_datetime DESC";

        $items = $this->query(
            $sql,
            [
                'list_ids'   => $lists,
                'date'       => $date,
                'date2'      => $date2,
                'contact_id' => $contact_id,
            ]
        )->fetchAll();

        return $items;
    }

    /**
     * @return string
     */
    private function getQuery()
    {
        return "SELECT
                  i.*,
                  IF(uf.contact_id, 1, 0) favorite,
                  /*pl.name list_name,
                  pl.sort list_sort,
                  pl.type list_type,
                  pl.icon list_icon,
                  pl.archived list_archived,
                  pl.hash list_hash,
                  pl.color list_color,*/
                  (select count(*) from pocketlists_attachment pa where pa.item_id = i.id) attachments_count,
                  (select count(*) from pocketlists_comment pc where pc.item_id = i.id) comments_count,  
                  (select count(*) from pocketlists_item_link pil where pil.item_id = i.id) linked_entities_count  
                FROM {$this->table} i
                LEFT JOIN pocketlists_user_favorites uf ON uf.contact_id = i:contact_id AND uf.item_id = i.id
                /*LEFT JOIN (select i2.name, l2.*
                  from pocketlists_list l2
                         JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) pl ON pl.id = i.list_id*/
                 ";
    }

    /**
     * @param      $list_id
     * @param bool $tree
     *
     * @return array|mixed
     */
    public function getAllByList($list_id, $tree = true)
    {
        $sql = $this->getQuery()."
                WHERE i.list_id = i:lid
                ORDER BY i.parent_id, i.sort ASC, i.id DESC";

        return $this->getItems($sql, $list_id, $tree);
    }

    /**
     * @param      $list_id
     * @param bool $tree
     *
     * @return array|mixed
     */
    public function getUndoneByList($list_id, $tree = true)
    {
        $sql = $this->getQuery()."
                WHERE i.list_id = i:lid AND i.status = 0
                ORDER BY i.parent_id, i.sort ASC, i.id DESC";

        return $this->getItems($sql, $list_id, $tree);
    }

    /**
     * @param      $list_id
     * @param int  $offset
     * @param int  $limit
     * @param bool $tree
     *
     * @return array|mixed
     */
    public function getDoneByList($list_id, $offset = 0, $limit = 10, $tree = true)
    {
        $sql = $this->getQuery()."
                WHERE i.list_id = i:lid AND i.status > 0
                ORDER BY i.complete_datetime DESC, i.parent_id, i.sort ASC, i.id DESC
                LIMIT {$limit}
                OFFSET {$offset}";

        return $this->getItems($sql, $list_id, $tree);
    }

    /**
     * @param      $list_id
     * @param bool $tree
     *
     * @return array|mixed
     */
    public function getArchiveByList($list_id, $tree = true)
    {
        $sql = $this->getQuery()."
                WHERE i.list_id = i:lid AND i.status < 0
                ORDER BY i.parent_id, i.sort ASC, i.id DESC";

        return $this->getItems($sql, $list_id, $tree);
    }

    /**
     * @param $sql
     * @param $list_id
     * @param $tree
     *
     * @return array|mixed
     */
    private function getItems($sql, $list_id, $tree)
    {
        $items = $this->query(
            $sql,
            [
                'lid'        => $list_id,
                'contact_id' => wa()->getUser()->getId(),
            ]
        )->fetchAll('id');

        return $items;
    }

    /**
     * @param $items
     * @param $tree
     *
     * @return array
     */
    private function getTree($items, $tree)
    {
        $result = [];

        if (!$items) {
            return $result;
        }

        foreach ($items as $id => $item) {
            $result[$item['id']] = $item;
            $result[$item['id']]['childs'] = [];
        }

        foreach ($result as $id => $item) {
            $result[$item['parent_id']]['childs'][$id] =& $result[$id];
        }
        if ($tree === true) {
            $result = isset($result[0]) ? $result[0]['childs'] : [];
        } elseif (is_numeric($tree)) {
            $result = isset($result[$tree]) ? [$tree => $result[$tree]] : [];
        }

        return $result;
    }

    /**
     * @param array $contact_ids
     *
     * @return array
     */
    public function getLastActivities($contact_ids = [])
    {
        $by_contact = "";
        if ($contact_ids && !is_array($contact_ids)) {
            $contact_ids = [$contact_ids];
            $by_contact = " WHERE t.contact_id IN (i:contact_id)";
        }

        // ох что-то я сомневаюсь
        $q = "SELECT
              MAX(t.last_date) last_activity_datetime,
              t.contact_id contact_id
            FROM (
                  SELECT
                    i.complete_contact_id contact_id,
                    max(i.complete_datetime) last_date
                  FROM {$this->table} i
                  GROUP BY i.complete_contact_id

                  UNION

                  SELECT
                    i.contact_id contact_id,
                    max(i.create_datetime) last_date
                  FROM {$this->table} i
                  GROUP BY i.contact_id
                  
                  UNION

                  SELECT
                    c.contact_id contact_id,
                    max(c.create_datetime) last_date
                  FROM pocketlists_comment c
                  GROUP BY c.contact_id
              ) t
            {$by_contact}
            GROUP BY t.contact_id";

        return $this->query(
            $q,
            ['contact_id' => $contact_ids]
        )->fetchAll('contact_id', 1);
    }

    /**
     * @param $contact_id
     *
     * @return array
     */
    public function getAssignedOrCompletesByContactItems($contact_id)
    {
        $lists = [];
        pocketlistsRBAC::filterListAccess($lists, $contact_id);
        $list_sql = pocketlistsRBAC::filterListAccess($lists);
        $q = "SELECT
                  i.id id,
                  i.parent_id parent_id,
                  i.has_children has_children,
                  i.name name,
                  i.note note,
                  i.status status,
                  i.priority priority,
                  i.calc_priority calc_priority,
                  i.contact_id contact_id,
                  i.create_datetime create_datetime,
                  i.due_date due_date,
                  i.due_datetime due_datetime,
                  i.complete_datetime complete_datetime,
                  i.complete_contact_id complete_contact_id,
                  i.assigned_contact_id assigned_contact_id,
                  i.key_list_id key_list_id,
                  l.id list_id,
                  l.icon list_icon,
                  l.color list_color,
                  l.name list_name,
                  IF(uf.contact_id, 1, 0) favorite
                FROM {$this->table} i
                LEFT JOIN (select i2.name, l2.*
                          from pocketlists_list l2
                                 JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) l ON l.id = i.list_id AND l.archived = 0
                LEFT JOIN pocketlists_user_favorites uf ON uf.contact_id = i:user_contact_id AND uf.item_id = i.id
                WHERE
                  (
                    i.assigned_contact_id = i:contact_id AND i.status >= 0 /* assigned to contact no matter who it completed */
                    OR i.contact_id = i:contact_id AND i.status >= 0 /* created by contact (completed and not) */
                    OR i.complete_contact_id = i:contact_id AND i.status > 0 /* completed by contact */
                  )
                  AND {$list_sql}
                GROUP BY id
                ORDER BY
                  i.status,
                  (i.complete_datetime IS NULL), i.complete_datetime DESC";
        $items = $this->query(
            $q,
            [
                'contact_id'      => $contact_id,
                'list_ids'        => $lists,
                'user_contact_id' => wa()->getUser()->getId(),
            ]
        )->fetchAll();

        return $items;
    }

    /**
     * @param $contact_id
     *
     * @return array
     */
    public function countAssignedOrCompletesByContactItems($contact_id)
    {
        $lists = [];
        pocketlistsRBAC::filterListAccess($lists, $contact_id);
        $list_sql = pocketlistsRBAC::filterListAccess($lists);
        $q = "SELECT
                  count(i.id) count_items,
                  if(max(i.calc_priority) is null, 0, max(i.calc_priority)) item_max_priority
                FROM {$this->table} i
                LEFT JOIN (select i2.name, l2.*
                          from pocketlists_list l2
                                 JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) l ON l.id = i.list_id AND l.archived = 0
                WHERE
                  ( i.assigned_contact_id = i:contact_id OR i.contact_id = i:contact_id                )
                  AND {$list_sql}
                  AND i.status = 0";
        $items = $this->query(
            $q,
            [
                'contact_id'      => $contact_id,
                'list_ids'        => $lists,
            ]
        )->fetchAssoc();

        return $items;
    }

    /**
     * @param string $app
     * @param string $entity_type
     * @param string $entity_id
     * @param array  $dateBounds
     *
     * @return array
     */
    public function getAppItems($app = '', $entity_type = '', $entity_id = '', $dateBounds = [])
    {
        $lists = [];
        $contact_id = wa()->getUser()->getId();
//        pocketlistsRBAC::filterListAccess($lists, $contact_id);
        $list_sql = 1;//pocketlistsRBAC::filterListAccess($lists);

        $appSql = '';
        if ($app) {
            $appSql = 'AND pil.app = s:app';
        }

        $appTypeSql = '';
        if ($entity_type) {
            $appTypeSql = 'AND pil.entity_type = s:type';
        }

        $entityIdSql = '';
        if ($entity_type) {
            $entityIdSql = 'AND pil.entity_id = i:entity_id';
        }

        $dateSql = '';
        if ($dateBounds) {
            if (isset($dateBounds[1])) {
                $dateSql = 'AND ((i.status = 0 AND (i.due_date BETWEEN s:date AND s:date2 OR DATE(i.due_datetime) BETWEEN s:date AND s:date2)) OR (i.status > 0 AND DATE(i.complete_datetime) BETWEEN s:date AND s:date2)) /* with due date or completed this day */';
            } elseif (isset($dateBounds[0])) {
                $dateSql = 'AND ((i.status = 0 AND (i.due_date = s:date OR DATE(i.due_datetime) = s:date)) OR (i.status > 0 AND DATE(i.complete_datetime) = s:date)) /* with due date or completed this day */';
            }
        }

        $q = $this->getQuery()."
                LEFT JOIN (select i2.name, l2.*
                          from pocketlists_list l2
                                 JOIN pocketlists_item i2 ON i2.id = l2.key_item_id) l ON l.id = i.list_id AND l.archived = 0
                JOIN pocketlists_item_link pil ON pil.item_id = i.id {$appSql} {$appTypeSql} {$entityIdSql}
                WHERE
                  {$list_sql}
                  {$dateSql}
                GROUP BY i.id
                ORDER BY
                  i.status,
                  (i.complete_datetime IS NULL), i.complete_datetime DESC";

        $items = $this->query(
            $q,
            [
                'contact_id'      => $contact_id,
                'list_ids'        => $lists,
                'user_contact_id' => wa()->getUser()->getId(),
                'app'             => $app,
                'type'            => $entity_type,
                'entity_id'       => $entity_id,
                'date'            => isset($dateBounds[0]) ? $dateBounds[0] : '',
                'date2'           => isset($dateBounds[1]) ? $dateBounds[1] : '',
            ]
        )->fetchAll();

        return $items;
    }

    /**
     * @param $contact_id
     * @param $when
     *
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getDailyRecapItems($contact_id, $when)
    {
        $now = time();
        $today = date("Y-m-d");
        $tomorrow = date("Y-m-d", strtotime("+1 day", $now));
        $seven_days = date("Y-m-d", strtotime("+7 days", $now));

        switch ($when) {
            case pocketlistsUserSettings::DAILY_RECAP_FOR_TODAY:
                $when = "(i.due_date <= '".$today."')";
                break;

            case pocketlistsUserSettings::DAILY_RECAP_FOR_TODAY_AND_TOMORROW:
                $when = "(i.due_date <= '".$tomorrow."')";
                break;

            case pocketlistsUserSettings::DAILY_RECAP_FOR_NEXT_7_DAYS:
                $when = "(i.due_date <= '".$seven_days."')";
                break;
        }

        $lists = pocketlistsRBAC::getAccessListForContact($contact_id);

        $sqlParts = $this->getTodoSql($contact_id, [], $lists);

        $sqlParts['and'][] = $when;

        $or_sql = implode("\n OR ", $sqlParts['or']);
        $and_sql = implode("\n AND ", $sqlParts['and']);
        $join_sql = implode("\n LEFT JOIN ", $sqlParts['join']);
        $select_sql = implode(",\n", $sqlParts['select']);

        $q = "SELECT
                  {$select_sql}
                FROM {$this->table} i
                {$join_sql}
                WHERE
                  ({$or_sql}) 
                  AND 
                  {$and_sql}
                GROUP BY i.id
                ORDER BY
                  i.status,
                  (i.complete_datetime IS NULL), i.complete_datetime DESC";

        $items = $this->query(
            $q,
            [
                'contact_id' => $contact_id,
                'list_ids'   => $lists,
            ]
        )->fetchAll();

        return $items;
    }

    /**
     * @param $name
     * @param $datetime
     *
     * @return array
     */
    public function getItemByNameAndCreatedDatetime($name, $datetime)
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE name = s:name AND 
 create_datetime BETWEEN (s:d1, s:d2) LIMIT 1",
            [
                'name' => $name,
                'd1'   => date('Y-m-d H:i:s', strtotime($datetime) - 60),
                'd2'   => date('Y-m-d H:i:s', strtotime($datetime) + 60),
            ]
        )->fetch();
    }

    /**
     * @param $user_last_activity
     *
     * @return array
     * @throws waDbException
     * @throws waException
     */
    public function getLastActivityItems($user_last_activity)
    {
        $user_id = wa()->getUser()->getId();
        $lists = [];
        $list_sql = pocketlistsRBAC::filterListAccess($lists);
        $q = "SELECT 
                     i.id item_id,
                     i.list_id list_id,
                     i.status status,
                     i.contact_id contact_id,
                     i.assigned_contact_id assigned_contact_id,
                     i.complete_contact_id complete_contact_id,
                     l.archived list_archived
                FROM pocketlists_item i
                LEFT JOIN pocketlists_list l ON l.id = i.list_id
                WHERE 
                    {$list_sql}
                    AND (
                      i.create_datetime > s:user_last_activity 
                      OR i.complete_datetime > s:user_last_activity
                    )";

        $items = $this->query(
            $q,
            [
                'list_ids'           => $lists,
                'user_last_activity' => $user_last_activity,
            ]
        )->fetchAll();

        $result = [
            'list'    => [],
            'team'    => [],
            'archive' => 0,
            'logbook' => 0,
        ];
        foreach ($items as $item) {
            if ($item['list_id'] && $item['contact_id'] != $user_id && $item['status'] == 0) {
                if (!isset($result['list'][$item['list_id']])) {
                    $result['list'][$item['list_id']] = 0;
                }
                $result['list'][$item['list_id']]++;
            }
            if ($item['assigned_contact_id'] && $item['assigned_contact_id'] != $user_id && $item['status'] == 0) {
                if (!isset($result['team'][$item['assigned_contact_id']])) {
                    $result['team'][$item['assigned_contact_id']] = 0;
                }
                $result['team'][$item['assigned_contact_id']]++;
            }
            if ($item['list_archived'] && $item['status'] == 0) {
                $result['archive']++;
            }
            if ($item['status'] && $item['complete_contact_id'] != $user_id) {
                $result['logbook']++;
            }
        }

        return $result;
    }

    /**
     * @param string $app
     *
     * @return int
     */
    public function getCountForApp($app)
    {
        $count = (int)$this->query(
            "SELECT COUNT(lid) cnt
             FROM (
                SELECT l.item_id lid 
                FROM {$this->table} i 
                JOIN pocketlists_item_link l ON i.id = l.item_id AND i.status = 0
                WHERE app = s:app 
                GROUP BY l.item_id) t",
            ['app' => $app]
        )->fetchField('cnt');

        return $count;
    }

    /**
     * @param $id
     *
     * @return array|bool
     */
    public function getAllByPocket($id)
    {
        if (!$id) {
            return false;
        }

        $result = $this->query(
            "SELECT i.*
             FROM {$this->table} i
             JOIN pocketlists_list l ON l.id = i.list_id
             WHERE l.pocket_id = i:pocket_id",
            ['pocket_id' => $id]
        );

        return $result->fetchAll('id');
    }

    /**
     * обновляем приоритеты в зависимости от текущего времени
     */
    public function updateCalcPriority()
    {
        $itemsWithDue = $this->where('due_date is not null or due_datetime is not null')->fetchAll('id');
        foreach ($itemsWithDue as $item) {
            $newCalcPriority = max(
                pocketlistsHelper::calcPriorityOnDueDate($item['due_date'], $item['due_datetime']),
                $item['priority']
            );

            if ($item['calc_priority'] != $newCalcPriority) {
                $this->updateById($item['id'], ['calc_priority' => $newCalcPriority]);
            }
        }
    }

    /**
     * @param array|int $value
     *
     * @return array|mixed|null
     */
    public function getById($value)
    {
        $all = !is_array($this->id) && is_array($value);
        $sql = $this->getQuery().'
                WHERE i.id IN (i:ids)';

        $items = $this->query(
            $sql,
            ['contact_id' => pl2()->getUser()->getContact()->getId(), 'ids' => $value]
        )->fetchAll('id');

        return $all ? $items : reset($items);
    }
}
