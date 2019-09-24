<?php
/**
 * Header template.
 *
 * @package LearningCommonsImporter
 */

// Load the admin header, which we skipped earlier in `on_load`.
require_once ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">

<?php do_action( 'learning_commons_importer_ui_header' ); ?>
