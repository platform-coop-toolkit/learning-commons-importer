<?php
/**
 * Page for the actual import step.
 *
 * @package LearningCommonsImporter
 */

$args = array(
	'action' => 'resource-import',
	'id'     => $this->id,
);
$url  = add_query_arg( urlencode_deep( $args ), admin_url( 'admin-ajax.php' ) );

$script_data = array(
	'count'   => array(
		'posts' => $data->resource_count,
		'terms' => $data->term_count,
	),
	'url'     => $url,
	'strings' => array(
		'complete' => __( 'Import complete!', 'learning-commons-importer' ),
	),
);

$url = LEARNING_COMMONS_IMPORTER_URL . 'dist/js/import.js';
wp_enqueue_script( 'resource-importer-import', $url, [ 'jquery' ], LEARNING_COMMONS_IMPORTER_VERSION, true );
wp_localize_script( 'resource-importer-import', 'resourceImportData', $script_data );

$this->render_header();
?>
<div class="welcome-panel">
	<div class="welcome-panel-content">
		<h2><?php esc_html_e( 'Step 3: Importing', 'learning-commons-importer' ); ?></h2>
		<div id="import-status-message" class="notice notice-info"><?php esc_html_e( 'Now importing.', 'learning-commons-importer' ); ?></div>
		<table class="import-status">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Import Summary', 'learning-commons-importer' ); ?></th>
					<th><?php esc_html_e( 'Completed', 'learning-commons-importer' ); ?></th>
					<th><?php esc_html_e( 'Progress', 'learning-commons-importer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<span class="dashicons dashicons-archive"></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: Number of resources when there's only one resource (1). %d: Number of resources. */
								_n( '%d resource', '%d resources', $data->resource_count, 'learning-commons-importer' ),
								$data->resource_count
							)
						);
						?>
					</td>
					<td>
						<span id="completed-posts" class="completed">0/0</span>
					</td>
					<td>
						<progress id="progressbar-posts" max="100" value="0"></progress>
						<span id="progress-posts" class="progress">0%</span>
					</td>
				</tr>
				<tr>
					<td>
						<span class="dashicons dashicons-category"></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: Number of terms when there's only one term (1). %d: Number of terms. */
								_n( '%d topic', '%d topics', $data->term_count, 'learning-commons-importer' ),
								$data->term_count
							)
						);
						?>
					</td>
					<td>
						<span id="completed-terms" class="completed">0/0</span>
					</td>
					<td>
						<progress id="progressbar-terms" max="100" value="0"></progress>
						<span id="progress-terms" class="progress">0%</span>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="import-status-indicator">
			<div class="progress">
				<progress id="progressbar-total" max="100" value="0"></progress>
			</div>
			<div class="status">
				<span id="completed-total" class="completed">0/0</span>
				<span id="progress-total" class="progress">0%</span>
			</div>
		</div>
	</div>
</div>

<table id="import-log" class="widefat">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Type', 'learning-commons-importer' ); ?></th>
			<th><?php esc_html_e( 'Message', 'learning-commons-importer' ); ?></th>
		</tr>
	</thead>
	<tbody>
	</tbody>
</table>

<?php

$this->render_footer();
