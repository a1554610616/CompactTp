<?php

namespace Framework\Db\Source;

use CompactTp\Lib\Log;

class Database extends \Framework\Db\Source
{
    const
        MODIFIER_INSERT = 'INSERT INTO',
        MODIFIER_INSERT_IGNORE = 'INSERTIGNORE INTO',
        MODIFIER_REPLACE = 'REPLACE INTO';
    public $sth, $clauses = array(), $params = array(), $fetch_mode = 0;

    public static $options = array(
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => null,
        'username' => 'root',
        'password' => null,
        'options' => array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,

            #overwrite 'options' if not using MySQL
            \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, //1000
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8', //1002
            \PDO::MYSQL_ATTR_FOUND_ROWS => true, //1008
        ),
    );

    public function __construct(array $options = array())
    {
        parent::__construct($options);
        $this->initial();
    }

    /**
     * @param array $options
     */
    public function connect(array $options)
    {
        $options = static::option($options);
        try {
            $this->link = new \PDO(
                "{$options['driver']}:host={$options['host']};port={$options['port']};dbname={$options['dbname']}",
                $options['username'],
                $options['password'],
                $options['options']
            );
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage());
            //\Framework\Core\Exception::handle($e);
        }
    }

    /**
     * @param $options
     * @return string
     */
    public static function instanceKey($options)
    {
        return $options['host'] . ':' . $options['port'] . ':' . $options['dbname'];
    }

    /**
     * @return $this
     */
    public function initial()
    {
        $this->clauses = array(
            'fields' => '*',
            'table' => '',
            'join' => '',
            'where' => ' WHERE 1',
            'group' => '',
            'having' => '',
            'order' => '',
            'limit' => '',
        );
        $this->params = array();
        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */
    public function field($fields)
    {
        $this->clauses['fields'] = $fields;
        return $this;
    }

    /**
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        $this->clauses['table'] = $table;
        return $this;
    }

    /**
     * where('gender', 'male')
     *
     * where('email', 'LIKE', '%@abc.com', 'OR')
     *
     * handle as a full clause when has "?"
     * where('(age >= ? OR age <= ?)', array(18, 30))
     *
     * @param $field
     * @param $operator
     * @param $value
     * @param $glue
     * @return Database
     */
    public function where($field, $operator, $value = null, $glue = 'AND')
    {

        if ($value === null) {

            $value = $operator;
            if (strpos($field, '?') === false) {
                $operator = '= ?';
            } else {
                $operator = '';
            }

            if (strpos($value, '>=') !== false) {
                $operator = '' . $value;
            }

        } else {

            if (strtoupper($operator) == 'IN') {
                $in = substr(str_repeat(',?', count($value)), 1);
                $operator = "IN ($in)";
            } elseif (strtoupper($operator) == 'AND' || strtoupper($operator) == 'OR') {
                $operator = $field . " > '" . $value[0] . "' " . $operator . " " . $field . " < '" . $value[1] . "'";
            } else {
                $operator .= ' ?';
            }
        }

        $this->clauses['where'] .= " $glue $field $operator";

        // var_dump('aaa',$field,$operator,$value,strpos($value,'between'),$this->clauses['where'],'bbb');

        if (is_array($value))
            $this->params = array_merge($this->params, $value);
        else {
            if (strpos($value, '>=') === false) {
                $this->params[] = $value;
            }
        }


        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return Database
     */
    public function limit($limit, $offset = 0)
    {
        if ($limit)
            $this->clauses['limit'] = ' LIMIT ' . $offset . ', ' . $limit;

        return $this;
    }

    /**
     * @param $group
     * @return Database
     */
    public function groupBy($group)
    {
        if ($group)
            $this->clauses['group'] = ' GROUP BY ' . $group;

        return $this;
    }

    /**
     * @param string|array $order
     *          - 'id'
     *          - array('id', 'ts' => -1)
     *
     * @return Database
     */
    public function orderBy($order)
    {
        if (!$order)
            return $this;

        $clause = ' ORDER BY ';

        if (is_array($order)) {
            $glue = '';

            foreach ($order as $col => $expr) {
                if (\is_int($col)) {
                    $col = $expr;
                    $expr = 'ASC';
                } elseif (-1 == $expr) {
                    $expr = 'DESC';
                }

                $clause .= $glue . $col . ' ' . $expr;
                $glue = ', ';
            }
        } else {
            $clause .= $order;
        }

        $this->clauses['order'] = $clause;

        return $this;
    }

    /**
     * @param $where
     * @return $this
     */
    public function having($where)
    {
        if ($where)
            $this->clauses['having'] = ' HAVING  ' . $where;

        return $this;
    }

    /**
     * @param array $join
     *          - array('comment' => 'blog.comment_id=comment.id')
     *          - array('comment' => array('on' => 'blog.comment_id=comment.id', 'type' => 'INNER JOIN'))
     *
     * @return string
     */
    public function join(array $join)
    {
        if (!$join)
            return $this;

        $clause = '';
        foreach ($join as $table => $expr) {
            \is_array($expr) or $expr = array('on' => $expr, 'type' => 'INNER JOIN');

            $clause .= ' ' . $expr['type'] . ' `' . $table . '` ON ' . $expr['on'];
        }

        $this->clauses['join'] = $clause;

        return $this;
    }

    /**
     * @param $data
     * @return int
     */
    public function update(array $data)
    {
        $value = $params = array();
        foreach ($data as $col => $val) {
            $value[] = '`' . $col . '`= ?';
            $params[] = $val;
        }

        // adjust order
        $this->params = array_merge($params, $this->params);

        $this->query('UPDATE ' . $this->clauses['table'] . ' SET ' . \implode(', ', $value) . $this->clauses['where'] . ';', $this->params);

        return $this->rowCount();
    }

    /**
     * @param $data
     * @param string $modifier
     * @return mixed
     */
    public function insert(array $data, $modifier = null)
    {
        $col = $value = array();
        foreach ($data as $k => $v) {
            $col[] = '`' . $k . '`';

            $value[] = '?';

            $this->params[] = $v;
        }

        $modifier or $modifier = self::MODIFIER_INSERT;

        $ret = $this->query($modifier . ' ' . $this->clauses['table'] . ' (' . \implode(', ', $col) . ') VALUES (' . \implode(', ', $value) . ');', $this->params);

        if ($ret) {
            $id = $this->lastInsertId();
            if ($id)
                return $id;
        }

        return $ret;
    }

    /**
     * @return int
     */
    public function delete()
    {
        $this->query('DELETE FROM ' . $this->clauses['table'] . $this->clauses['where'] . ';', $this->params);
        return $this->rowCount();
    }

    /**
     * @param $query
     * @param array $params
     * @return mixed
     */
    public function fetch($query, array $params = array())
    {
        $this->query($query, $params);

        return $this->setPDOFetchMode()->fetch();
    }

    /**
     * @param $query
     * @param array $params
     * @return mixed
     */
    public function fetchAll($query, array $params = array())
    {
        $this->query($query, $params);
        return $this->setPDOFetchMode()->fetchAll();
    }

    /**
     * @param $query
     * @param array $params
     * @return mixed
     */
    public function query($query, array $params = array())
    {
        $this->sth = $this->link->prepare($query);
        $result = $this->sth->execute($params);
        $this->initial();
        // var_dump($this->getLastSql(), $params);
        //记录sql语句
        $name = php_sapi_name();
        if ($name != 'cli') {
            $this->writeSqlLog($this->getLastSql(), $params);
        }
        return $result;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getSelectClause()
    {
        $c = &$this->clauses;
        return 'SELECT ' . $c['fields'] . ' FROM ' . $c['table'] . $c['join'] . $c['where'] . $c['group'] . $c['having'] . $c['order'] . $c['limit'] . ';';
    }

    /**
     * @param $mode
     * @return $this
     */
    public function setFetchMode($mode)
    {
        $this->fetch_mode = $mode;
        return $this;
    }

    /**
     * @return statement
     */
    protected function setPDOFetchMode()
    {
        if ($this->fetch_mode) {
            if (is_array($this->fetch_mode))
                call_user_func_array(array($this->sth, 'setFetchMode'), $this->fetch_mode);
            else
                $this->sth->setFetchMode($this->fetch_mode);
        }

        return $this->sth;
    }

    /**
     * @return mixed
     */
    public function dumpParams()
    {
        return $this->sth->debugDumpParams();
    }

    /**
     * @return string
     */
    public function getLastSql()
    {
        return $this->sth->queryString;
    }

    /**
     * @param $name
     * @return int
     */
    public function lastInsertId($name = null)
    {
        return $this->link->lastInsertId($name);
    }

    /**
     * @return array
     */
    public function errorInfo()
    {
        return $this->sth->errorInfo();
    }

    /**
     * @return int
     */
    public function rowCount()
    {
        return $this->sth->rowCount();
    }

    public function setCharset($charset)
    {
        $this->query('SET NAMES ' . $charset . ';');

        return $this;
    }

    public function setAttribute($attr, $val)
    {
        return $this->link->setAttribute($attr, $val);
    }

    private function writeSqlLog($sql, $params)
    {
        $value = $last_sql = '';
        preg_match_all('/\?/', $sql, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $key => $value) {
                if (strpos($sql, '?') !== false) {
                    $position = strpos($sql, '?');
                    $old = substr($sql, 0, $position + 1);
                    $new = substr_replace($sql, "'" . $params[$key] . "'", $position);
                    $sql = str_replace($old, $new, $sql);
                }
            }
        }
        $msg = "\t" . $sql;
        Log::addSqlLog($msg);
    }


    public function close()
    {
        $this->link = null;
        return $this;
    }

    public function __destruct()
    {
        $this->close();
    }
}