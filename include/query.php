<?php
/**
 * WP Twitter Stream.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */

/**
 * WP Twitter Stream Db Query.
 *
 * @package WP_Twitter_Stream
 * @author Vince Tikász <vince.tikasz@gmail.com>
 * @license GPL-2.0+
 * @link http://vince.tikasz.hu
 * @copyright 2013 Vince Tikász
 */
class WP_Twitter_Stream_Query {

  /** Define filter mode constants */
  const FILTER_MODE_ALL = 0;
  const FILTER_MODE_EXCLUDE = 1;
  const FILTER_MODE_INCLUDE = 2;

  /**
   * Filter mode
   * Available values the constants defined by this class.
   * @var int
   */
  protected $filter_mode = 0;

  /**
   * Fields to query
   * @var array
   */
  protected $fields = array();

  /**
   * Distinct query
   * @var bool
   */
  protected $distinct = false;

  /**
   * Run query on these tables.
   * @var array
   * @see WP_Twitter_Stream_Query::_join()
   */
  protected $table = array();

  /**
   * Query conditions
   * @var array
   */
  protected $where = array();

  /**
   * Filter for these hashtag ids.
   * Combination with $filter_mode determine some query conditions.
   * @var array
   */
  protected $hashtag_ids = array();

  /**
   * Query orders.
   * @var array
   */
  protected $orders = array();

  /**
   * Query limit.
   * @var int
   */
  protected $limit = 10;

  /**
   * Constructor.
   */
  public function __construct() {
    // This is a tweet query builder. Every query is a tweet query so
    // we set up tweet as base table
    $this->_join('tweets', WP_Twitter_Stream_Db::$tweets, 'FROM', '');
    $this->add_fields('tweets', '*');
    $this->set_order('`tweets`.`time`', 'DESC');
  }

  /**
   * Builds query run it and returns the result.
   *
   * @param string $output
   *   (optional) Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants.
   *   With one of the first three, return an array of rows indexed from 0 by
   *   SQL result row number.
   *   Each row is an associative array (column => value, ...), a numerically
   *   indexed array (0 => value, ...), or an object. ( ->column = value ),
   *   respectively.
   *   With OBJECT_K, return an associative array of row objects keyed by the
   *   value of each row's first column's value. Duplicate keys are discarded.
   *
   * @return mixed
   *   Database query results
   */
  public function get_result($output = ARRAY_A) {
    $query = $this->to_string();
    return WP_Twitter_Stream_Db::wpdb()->get_results($query, $output);
  }

  /**
   * @param int $filter_mode
   * @return WP_Twitter_Stream_Query
   */
  public function set_filter_mode($filter_mode) {
    $enabled = array(
      self::FILTER_MODE_ALL,
      self::FILTER_MODE_EXCLUDE,
      self::FILTER_MODE_INCLUDE,
    );
    if (in_array($filter_mode, $enabled)) {
      $this->filter_mode = $filter_mode;
    }
    return $this;
  }

  /**
   * @return int
   */
  public function get_filter_mode() {
    return $this->filter_mode;
  }

  /**
   * @param array $hastag_ids
   * @param int $filter_mode
   * @return WP_Twitter_Stream_Query
   */
  public function set_hashtag_ids(array $hastag_ids, $filter_mode) {
    $this->set_filter_mode($filter_mode);
    $this->hashtag_ids = array_unique(array_map('intval', $hastag_ids));
    if (
      count($this->hashtag_ids)
      && in_array($filter_mode, array(self::FILTER_MODE_EXCLUDE, self::FILTER_MODE_INCLUDE))
    ) {
      $connect = WP_Twitter_Stream_Db::$tw2ht;
      $this->distinct = true;
      $placeholders = join(', ', array_fill(0, count($this->hashtag_ids), '%d'));

      if ($this->filter_mode == self::FILTER_MODE_INCLUDE) {
        $this->left_join('ht', $connect, '`tweets`.`id` = `ht`.`tid`');
        $this->add_condition("`ht`.`hid` IN ({$placeholders})", $this->hashtag_ids, 'hashtags_include');
      }
      else {
        $condition = "`tweets`.`id` NOT IN (SELECT `tid` FROM `{$connect}` WHERE `hid` IN ({$placeholders}) GROUP BY `tid`)";
        $this->add_condition($condition, $this->hashtag_ids, 'hashtags_exclude');
      }
    }
    return $this;
  }

  /**
   * Sets query limit.
   * @param int $limit
   * @return WP_Twitter_Stream_Query
   */
  public function set_limit($limit) {
    if (intval($limit) > 0) {
      $this->limit = $limit;
    }
    return $this;
  }

  /**
   * Sets distinct flag
   * @param bool $distinct
   * @return WP_Twitter_Stream_Query
   */
  public function set_distinct($distinct) {
    $this->distinct = (bool) $distinct;
    return $this;
  }

  /**
   * Left join a table to this query.
   *
   * @param string $alias
   *   Table alias
   * @param string $table
   *   Table name
   * @param string $on
   *   Join condition
   *
   * @return WP_Twitter_Stream_Query
   */
  public function left_join($alias, $table, $on) {
    return $this->_join($alias, $table, 'LEFT JOIN', $on);
  }

  /**
   * Right join a table to this query.
   *
   * @param string $alias
   *   Table alias
   * @param string $table
   *   Table name
   * @param string $on
   *   Join condition
   *
   * @return WP_Twitter_Stream_Query
   */
  public function right_join($alias, $table, $on) {
    return $this->_join($alias, $table, 'RIGHT JOIN', $on);
  }

  /**
   * Inner join a table to this query.
   *
   * @param string $alias
   *   Table alias
   * @param string $table
   *   Table name
   * @param string $on
   *   Join condition
   *
   * @return WP_Twitter_Stream_Query
   */
  public function inner_join($alias, $table, $on) {
    return $this->_join($alias, $table, 'INNER JOIN', $on);
  }

  /**
   * Alias for WP_Twitter_Stream_Query::inner_join()
   * @param string $alias
   *   Table alias
   * @param string $table
   *   Table name
   * @param string $on
   *   Join condition
   *
   * @return WP_Twitter_Stream_Query
   */
  public function join($alias, $table, $on) {
    return $this->inner_join($alias, $table, $on);
  }

  /**
   * Join a table to this query.
   *
   * @param string $alias
   *   Table alias
   * @param string $table
   *   Table name
   * @param string $mode
   *   Join mode (LEFT | RIGHT | INNER | FROM)
   *   For the base table use FROM.
   * @param string $on
   *   Join condition
   *
   * @return WP_Twitter_Stream_Query
   */
  public function _join($alias, $table, $mode, $on) {
    $this->table[$alias] = array(
      'alias' => $alias,
      'name' => $table,
      'mode' => $mode,
      'on' => $on,
    );
    return $this;
  }

  /**
   * Adds new field to this query.
   *
   * @param string $table_alias
   *   The alias of the table the field from.
   * @param string|array $fields
   *   The field name or an array with $alias => $field pairs.
   * @param null|string $alias
   *   Field alias. Used if field is string.
   *
   * @return WP_Twitter_Stream_Query
   */
  public function add_fields($table_alias, $fields, $alias = null) {
    if (!isset($this->table[$table_alias])) {
      return $this;
    }

    if (!isset($this->fields[$table_alias])) {
      $this->fields[$table_alias] = array();
    }

    if (is_string($fields)) {
      if (!isset($alias)) {
        $alias = $fields;
      }
      $this->fields[$table_alias][$alias] = $fields;
    }
    elseif (is_array($fields)) {
      foreach ($fields as $alias => $field) {
        if (is_numeric($alias)) {
          $alias = null;
        }
        $this->add_fields($table_alias, $field, $alias);
      }
    }

    return $this;
  }

  /**
   * Adds a new where condition.
   *
   * @see wpdb::prepare()
   *
   * @param string $condition
   *   Query statement with sprintf()-like placeholders
   * @param array $args
   *   The array of variables to substitute into the query's placeholders if
   *   being called like {@link http://php.net/vsprintf vsprintf()}
   * @param string $name
   *   (optional) the condition name.
   *
   * @return WP_Twitter_Stream_Query
   */
  public function add_condition($condition, $args = null, $name = null) {
    $where = WP_Twitter_Stream_Db::wpdb()->prepare($condition, $args);
    if (isset($name) && $name) {
      $this->where[$name] = $where;
    }
    else {
      $this->where[] = $where;
    }
    return $this;
  }

  /**
   * Add new ordering logic.
   *
   * @param string $order_by
   *   The field name.
   * @param string $direction
   *   The ordering direction.
   *
   * @return WP_Twitter_Stream_Query
   */
  public function add_order($order_by, $direction = 'ASC') {
    if (!in_array($direction, array('ASC', 'DESC'))) {
      $direction = 'ASC';
    }
    $this->orders[] = $order_by . ' ' . $direction;
    return $this;
  }

  /**
   * Clean all previously defined orders and set a new one.
   *
   * @param $order
   *   The field name.
   * @param string $direction
   *   The ordering direction.
   *
   * @return WP_Twitter_Stream_Query
   */
  public function set_order($order, $direction = 'ASC') {
    $this->orders = array();
    return $this->add_order($order, $direction);
  }

  /**
   * Alias for WP_Twitter_Stream_Query::__toString()
   * @return string
   */
  public function to_string() {
    return $this->__toString();
  }

  /**
   * Alias for WP_Twitter_Stream_Query::__toString()
   * @return string
   */
  public function toString() {
    return $this->__toString();
  }

  /**
   * Query builder
   * @return string
   */
  public function __toString() {
    $fields = $this->get_query_fields();
    $table = $this->get_query_from();
    $where = $this->get_query_where();
    $order = $this->get_query_order();

    $sql = "SELECT\n{$fields}\nFROM\n{$table}\n{$where}\n{$order}\nLIMIT {$this->limit}";
    return $sql;
  }

  /**
   * Get SELECT SQL fields.
   * @return string
   */
  protected function get_query_fields() {
    $_fields = array();
    foreach ($this->fields as $tbl_alias => $fields) {
      foreach ($fields as $alias => $field) {
        $_field = "`{$tbl_alias}`.{$field}";
        if (!is_numeric($alias) && $field !== '*') {
          $_field .= " AS `{$alias}`";
        }
        $_fields[] = $_field;
      }
    }
    return ($this->distinct ? 'DISTINCT ' : '') . join(', ', $_fields);
  }

  /**
   * Get SELECT SQL FROM clause.
   * @return string
   */
  protected function get_query_from() {
    $_table = array();
    $_from = '';
    foreach ($this->table as $alias => $tbl) {
      $mode = $tbl['mode'];
      $on = "ON {$tbl['on']}";
      if ($mode == 'FROM') {
        $_from = $alias;
        $mode = '';
        $on = '';
      }
      $_table[$alias] = "{$mode} `{$tbl['name']}` AS `{$tbl['alias']}` {$on}";
    }
    $from = $_table[$_from];
    unset($_table[$_from]);
    return $from . "\n" . join("\n", $_table);
  }

  /**
   * Get SELECT SQL WHERE clause.
   * @return string
   */
  protected function get_query_where() {
    $_where = array();
    foreach ($this->where as $condition) {
      $_where[] = "({$condition})";
    }
    if (empty($_where)) {
      return '';
    }
    return "\nWHERE\n" . join("AND \n", $_where);
  }

  /**
   * Get SELECT SQL ORDER BY clause.
   * @return string
   */
  protected function get_query_order() {
    if (empty($this->orders)) {
      return '';
    }
    return "ORDER BY\n" . join(",\n", $this->orders);
  }
}
