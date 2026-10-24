<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Prints Elementor's Element_Base::print_element and should_render (GPL code as installed). */
if (!defined('ABSPATH')) exit;
foreach (array(array('Elementor\Element_Base', 'print_element'), array('Elementor\Element_Base', 'should_render'), array('Elementor\Widget_Base', 'should_render'), array('Elementor\Widget_Base', 'print_content')) as $cm) {
    if (!method_exists($cm[0], $cm[1])) { echo "## {$cm[0]}::{$cm[1]}: none\n"; continue; }
    $r = new ReflectionMethod($cm[0], $cm[1]);
    $l = file($r->getFileName());
    echo "## " . $r->getDeclaringClass()->getName() . "::{$cm[1]}\n" . implode('', array_slice($l, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1)) . "\n";
}
