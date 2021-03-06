<?php

namespace Framework\Db\Model;

class Database extends \Framework\Db\Model
{
    public $last_fetch_query = array();
    private $_fetch_mode = null;

    public function __construct()
    {
        parent::__construct();

        if ($this->fetch_mode === parent::FETCH_OBJECT) {
            $this->_fetch_mode = array(\PDO::FETCH_INTO, $this);
        } else {
            $this->_fetch_mode = \PDO::FETCH_ASSOC;
        }
        $this->options[':join'] = array();
        $this->options[':group'] = '';
        $this->options[':having'] = '';
    }

    /**
     * @param $options
     * @param array $query
     */
    public function connection($options, $query = array())
    {
        return $this->link = new \Framework\Db\Source\Database($options);
    }

    /**
     * @param $mode
     * @return mixed
     */
    public function setFetchMode($mode)
    {
        $mode or $mode = $this->_fetch_mode;

        return $this->link->setFetchMode($mode);
    }

    /**
     * @param $query
     * @param null $connection
     * DB_HOST* @param array $param
     * @return mixed
     */
    public function query($query, $connection = null, array $param = array())
    {
        $this->connection($connection, $query);

        return $this->link->query($query, $param);
    }

    /**
     * @param $query
     *          - 1 // means find $primary_key = 1
     *          - array('id' => array('<', 6), array('gender' => array('=', 'male', 'OR'), ':limit' => 5)
     *          - ':source',':conditions',':fields',':order',':limit',':page' was defined in $this->options
     * @param mixed $connection
     * @param array $params
     * @param array|null $mode
     * @return mixed
     */
    public function fetch($query, $connection = null, array $params = array(), $mode = null)
    {

        $this->connection($connection, $query);

        if (!is_string($query)) {
            $query = $this->getFetchQuery($query, $params);
            $params += $this->link->getParams();
        }
//
//        echo '<!--';
//        echo $query;
//        echo '-->';

        return $this->setFetchMode($mode)->fetch($query, $params);
    }

    /**
     * params see fetch()
     *
     * @param $query
     * @param mixed $connection
     * @param array $params
     * @param array|null $mode
     * @return mixed
     */
    public function fetchAll($query, $connection = null, array $params = array(), $mode = null)
    {
        $this->connection($connection, $query);

        if (!is_string($query)) {
            $query = $this->getFetchQuery($query, $params);
            $params += $this->link->getParams();
        }
        return $this->setFetchMode($mode)->fetchAll($query, $params);
    }

    /**
     * params see fetch()
     *
     * @param $query
     * @param $connection
     * @return mixed
     */
    public function fetchCount($query = null, $connection = null)
    {
        $query or $query = $this->last_fetch_query;

        $query[':fields'] = 'COUNT(*)';
        $query[':limit'] = 1;

        unset($query[':page']);

        return $this->fetch($query, $connection, array(), array(\PDO::FETCH_COLUMN, 0));
    }

    /**
     * @param $query
     * @return array
     */
    public function getFetchQuery($query)
    {
        //$query = $this->_resultQuery($query);

        $this->last_fetch_query = $query = $this->formatQuery($query);

        $this->link
            ->table($this->source($query[':source'], $query))
            ->field($query[':fields'])
            ->limit($query[':limit'], $query[':page'])
            ->groupBy($query[':group'])
            ->having($query[':having'])
            ->orderBy($query[':order'])
            ->join($this->join($query[':join'], $query));

        return $this->link->getSelectClause();
    }

    /**
     * @param array $query
     * @return array
     */
    public function formatQuery(array $query)
    {
        foreach ($query as $key => $val) {
            if (isset($this->options[$key]))
                continue;

            if (is_array($val)) {
                is_int($key) or array_unshift($val, $key);

                $val += array('', '', '', 'AND');

                $this->link->where($val[0], $val[1], $val[2], $val[3]);
            } else {
                $this->link->where($key, $val);
            }
        }

        $query += $this->options;

        return $query;
    }

    /**
     * @param array $data
     * @param array $query
     *              - see fetch()
     * @param null $connection
     * @param null $modifier
     * @return mixed
     */
    public function insert(array $data, array $query = array(), $connection = null, $modifier = null)
    {
        $this->connection($connection, $data);

        $query = $this->formatQuery($query);

        return $this->link->table($this->source($query[':source'], $data))->insert($data, $modifier);
    }

    /**
     * @param $data
     * @param array $condition $query
     *              - see fetch()
     * @param null $connection
     * @return mixed
     */
    public function update(array $data = array(), array $condition = array(), $connection = null)
    {
        $data = $this->resultSet($data);

        foreach ($condition as $k => $v)
            $query[$k] = $v;

        $this->connection($connection, $data);

        $query = $this->formatQuery($query);

        return $this->link->table($this->source($query[':source'], $data))->update($data);
    }

    /**
     * @param array $query
     *              - see fetch()
     * @param null $connection
     * @return mixed
     */
    public function delete($query = array(), $connection = null)
    {
        $query = $this->_resultQuery($query);

        $this->connection($connection, $query);

        $query = $this->formatQuery($query);

        return $this->link->table($this->source($query[':source'], $query))->delete();
    }

    /**
     * just overwrite it
     *
     * @param $source
     * @param $data
     * @return string returns table name
     */
    public function source($source, $data)
    {
        return $source;
    }

    /**
     * @return int
     */
    public function lastInsertId()
    {
        return $this->link->lastInsertId();
    }

    /**
     * @return string
     */
    public function getLastSql()
    {
        return $this->link->getLastSql();
    }

    /**
     *
     * \Parith\Data\Model\Database::join('comment')
     *
     * @param $join
     * @param $query
     * @return array
     * @throws \Parith\Exception
     */
    public function join($join, $query)
    {
        $ret = array();

        if ($join) {
            $join = (array)$join;
            foreach ($join as $key => $name) {

                if (is_string($key)) {
                    $ret[$key] = &$name;
                    continue;
                }

                $relation = &$this->relations[$name];

                if ($relation) {
                    $ret[$relation['class']->source($query[':source'], $query)] = array(
                        'on' => key($relation['key']) . '=' . current($relation['key']),
                        'type' => 'INNER JOIN',
                    );
                } else
                    throw new \Framework\Core\Exception('Undefined relation "' . $name . '"');
            }
        }

        return $ret;
    }

    private function _resultQuery($query)
    {
        if ($query) {
            if (is_array($query))
                $query = $this->resultSet($query);
            else
                $query = $this->resultSet($this->primary_key, $query);
        }

        return $query;
    }
}

