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
  const FILTER_MEDIA_DO_NOT_FILTER = 0;
  const FILTER_MEDIA_ONLY_WITH_MEDIA = 1;
  const FILTER_MEDIA_EXCLUDE_WITH_MEDIA = 2;

  /**
   * Filter mode
   * Available values the constants defined by this class.
   * @var int
   */
  protected $filter_mode = self::FILTER_MODE_ALL;

  /**
   * Media filter mode
   * Available values the constants defined by this class.
   * @var int
   */
  protected $media_filter = self::FILTER_MEDIA_DO_NOT_FILTER;

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
   * Having clauses
   * @var array
   */
  protected $having = array();

  /**
   * Group by
   * @var array
   */
  protected $group_by = array();

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
   * Print query.
   * @var bool
   */
  protected $dump_query = false;

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
    if ($this->dump_query) {
      $dump = new WP_Twitter_Stream_Dump($query);
      echo $dump->output();
    }
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
   * @param int $media_filter
   * @return WP_Twitter_Stream_Query
   */
  public function set_media_filter($media_filter) {
    $enabled = array(
      self::FILTER_MEDIA_DO_NOT_FILTER,
      self::FILTER_MEDIA_ONLY_WITH_MEDIA,
      self::FILTER_MEDIA_EXCLUDE_WITH_MEDIA,
    );
    if (in_array($media_filter, $enabled)) {
      $this->media_filter = $media_filter;
    }
    return $this;
  }

  /**
   * @return int
   */
  public function get_media_filter() {
    return $this->media_filter;
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
      $placeholders = join(', ', array_fill(0, count($this->hashtag_ids), '%d'));

      $this->left_join('ht', $connect, '`tweets`.`id` = `ht`.`tid`');
      $this->add_group_by('`tweets`.`id`', 0);

      $condition_name = 'hashtags';
      if ($this->filter_mode == self::FILTER_MODE_INCLUDE) {
        $condition = "`ht`.`hid` IN ({$placeholders})";
        $this->add_condition($condition, $this->hashtag_ids, $condition_name);
        unset($this->having[$condition_name]);
      }
      else {
        $this->add_fields('ht', 'hid');
        $condition = "`ht`.`hid` IS NULL OR `ht`.`hid` NOT IN ({$placeholders})";
        unset($this->where[$condition_name]);
        $this->add_having_condition($condition, $this->hashtag_ids, $condition_name);
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
      $this->limit = intval($limit);
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
   * Adds a new having condition.
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
  public function add_having_condition($condition, $args = null, $name = null) {
    $having = WP_Twitter_Stream_Db::wpdb()->prepare($condition, $args);
    if (isset($name) && $name) {
      $this->having[$name] = $having;
    }
    else {
      $this->having[] = $having;
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
   * Add new group by logic.
   *
   * @param string $group_by
   *   The field name.
   * @param int $weight
   *   The grouping weight.
   *
   * @return WP_Twitter_Stream_Query
   */
  public function add_group_by($group_by, $weight = 10) {
    $this->group_by[intval($weight) . '#' . $group_by] = $group_by;
    return $this;
  }

  /**
   * Clean all previously defined group by settings and set a new one.
   *
   * @param $group_by
   *   The field name.
   * @param int $weight
   *   The grouping weight.
   *
   * @return WP_Twitter_Stream_Query
   */
  public function set_group_by($group_by, $weight = 10) {
    $this->group_by = array();
    return $this->add_group_by($group_by, $weight);
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
    $fields = trim($this->get_query_fields());
    $distinct = $this->distinct ? ' DISTINCT' : '';
    $_fields = str_replace(', ', ",\n", $fields);

    // Start building query
    $sql = array(
      'SELECT' . $distinct . "\n" . preg_replace('%^%m', '  ',  $_fields),
      "FROM\n" . preg_replace('%^%m', '  ', trim($this->get_query_from())),
    );

    // Add WHERE clause if any.
    if ($where = trim($this->get_query_where())) {
      $sql[] = "WHERE\n" . preg_replace('%^%m', '  ', $where);
    }

    // Add GROUP BY clause if any.
    if ($group_by = trim($this->get_query_group_by())) {
      $sql[] = "GROUP BY\n" . preg_replace('%^%m', '  ', $group_by);
    }

    // Add HAVING clause if any.
    if ($having = trim($this->get_query_having())) {
      $sql[] = "HAVING\n" . preg_replace('%^%m', '  ', $having);
    }

    // Set ordering.
    if ($order = trim($this->get_query_order())) {
      $sql[] = "ORDER BY\n" . preg_replace('%^%m', '  ', $order);
    }

    // Limit the size of result set.
    if ($this->limit > 0) {
      $sql[] = 'LIMIT ' . $this->limit;
    }

    // Beautify the query.
    return "\n" . preg_replace("%\n+%", "\n", join("\n", $sql));
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
    return join(', ', $_fields);
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
    return join("\nAND ", $_where);
  }

  /**
   * Get SELECT SQL HAVING clause.
   * @return string
   */
  protected function get_query_having() {
    $_having = array();
    foreach ($this->having as $condition) {
      $_having[] = "({$condition})";
    }
    if (empty($_having)) {
      return '';
    }
    return join("\nAND ", $_having);
  }

  /**
   * Get SELECT SQL ORDER BY clause.
   * @return string
   */
  protected function get_query_order() {
    if (empty($this->orders)) {
      return '';
    }
    return join(",\n", $this->orders);
  }

  /**
   * Get SELECT SQL GROUP BY clause.
   * @return string
   */
  protected function get_query_group_by() {
    if (empty($this->group_by)) {
      return '';
    }

    return join(",\n", $this->group_by);
  }

  /**
   * Set print query flag.
   *
   * Setting this TRUE will cause any effect if debug mode is enabled.
   *
   * @param bool $dump_query
   * @return WP_Twitter_Stream_Query
   */
  public function set_dump_query($dump_query) {
    $this->dump_query = WP_Twitter_Stream_Plugin::is_debug_mode_enabled() && $dump_query;
    return $this;
  }

  /**
   * Add condition for exclude deleted tweets.
   * @return WP_Twitter_Stream_Query
   */
  public function exclude_deleted()  {
    unset($this->where['include_deleted']);
    $this->add_condition('`tweets`.`last_checked` IS NOT NULL', array(), 'exclude_deleted');
    return $this;
  }

  /**
   * Add condition for include deleted tweets.
   * @return WP_Twitter_Stream_Query
   */
  public function include_deleted()  {
    unset($this->where['exclude_deleted']);
    return $this;
  }

  /**
   * Add condition for get only deleted tweets.
   * @return WP_Twitter_Stream_Query
   */
  public function only_deleted() {
    unset($this->where['exclude_deleted']);
    $this->add_condition('`tweets`.`last_checked` IS NULL', array(), 'include_deleted');
    return $this;
  }

  /**
   * Add condition to exclude local tweet ids from result.
   * @param int|array $id
   *   One or a list of local tweets ids.
   * @return WP_Twitter_Stream_Query
   */
  public function exclude_id($id) {
    if (
      is_numeric($id)
      || (is_array($id) && !empty($id))
    ) {
      if (!is_array($id)) {
        $id = array($id);
      }
      $placeholders = join(', ', array_fill(0, count($id), '%d'));
      $this->add_condition("`tweets`.`id` NOT IN ({$placeholders})", $id, 'exlude_id');
    }
    return $this;
  }

  /**
   * Add condition to filter tweets by media.
   * @param $media_filter
   * @return WP_Twitter_Stream_Query
   */
  public function media_filter_condition($media_filter = null) {
    if (isset($media_filter)) {
      $this->set_media_filter($media_filter);
    }

    $column = '`tweets`.`has_media`';

    switch ($this->get_media_filter()) {
      case self::FILTER_MEDIA_EXCLUDE_WITH_MEDIA:
        unset($this->where['media_filter_include']);
        if (!isset($this->where['media_filter_exclude'])) {
          $this->add_condition($column . ' <> 1', array(), 'media_filter_exclude');
        }
        break;

      case self::FILTER_MEDIA_ONLY_WITH_MEDIA:
        unset($this->where['media_filter_exclude']);
        if (!isset($this->where['media_filter_include'])) {
          $this->add_condition($column . ' = 1', array(), 'media_filter_include');
        }
        break;

      case self::FILTER_MEDIA_DO_NOT_FILTER:
      default:
        unset($this->where['media_filter_include']);
        unset($this->where['media_filter_exclude']);
        break;
    }
    return $this;
  }

  /**
   * Set reply filter.
   * @param bool $enable
   * @return WP_Twitter_Stream_Query
   */
  public function add_reply_filter($enable) {
    $condition_name = 'reply_disable';
    if ($enable) {
      unset($this->where[$condition_name]);
    }
    else {
      $this->add_condition('`tweets`.`reply` <> 1', array(), $condition_name);
    }
    return $this;
  }
}
