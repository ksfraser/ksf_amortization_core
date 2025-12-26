<?php
namespace Ksfraser\Amortizations;

class SelectorDbAdapterWPDB implements SelectorDbAdapter {
    private $wpdb;
    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
    }
    public function query(string $sql) {
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    public function fetch_assoc($result) {
        // $result is already an array of associative arrays
        return $result;
    }
    public function escape($value) {
        return $this->wpdb->escape($value);
    }
    public function execute(string $sql, array $params = []): void {
        // For WPDB, use prepare and query for generic execution
        $prepared = $this->wpdb->prepare($sql, $params);
        $this->wpdb->query($prepared);
    }
}
