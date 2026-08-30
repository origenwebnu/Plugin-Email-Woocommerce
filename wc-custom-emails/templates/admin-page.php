<?php
/**
 * Admin page template.
 *
 * @package WC_Custom_Emails
 *
 * @var WC_Email[] $emails
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcce-admin-wrap">
	<h1><?php esc_html_e( 'Emails WC', 'wc-custom-emails' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Sube un archivo HTML personalizado para cada correo de WooCommerce. Cuando exista una plantilla personalizada, el sistema la usará en lugar de la plantilla estándar.', 'wc-custom-emails' ); ?>
	</p>

	<?php if ( isset( $_GET['wcce_notice'], $_GET['wcce_msg'] ) ) : ?>
		<?php
		$notice_type = 'success' === sanitize_key( wp_unslash( $_GET['wcce_notice'] ) ) ? 'success' : 'error';
		$notice_msg  = sanitize_text_field( wp_unslash( rawurldecode( wp_unslash( $_GET['wcce_msg'] ) ) ) );
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<p><?php echo esc_html( $notice_msg ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $emails ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No se encontraron emails de WooCommerce.', 'wc-custom-emails' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped wcce-emails-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Email', 'wc-custom-emails' ); ?></th>
					<th scope="col"><?php esc_html_e( 'ID', 'wc-custom-emails' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Estado', 'wc-custom-emails' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Placeholders', 'wc-custom-emails' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Acciones', 'wc-custom-emails' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $emails as $email ) : ?>
					<?php
					$has_custom     = WCCE_Storage::has_custom_template( $email->id );
					$template_meta  = WCCE_Storage::get_template_meta( $email->id );
					$placeholders   = WCCE_Email_Manager::get_placeholders( $email );
					$upload_action  = admin_url( 'admin-post.php' );
					$delete_url     = wp_nonce_url(
						add_query_arg(
							array(
								'action'   => 'wcce_delete_email',
								'email_id' => $email->id,
							),
							admin_url( 'admin-post.php' )
						),
						'wcce_delete_email'
					);
					$download_url   = wp_nonce_url(
						add_query_arg(
							array(
								'action'   => 'wcce_download_default',
								'email_id' => $email->id,
							),
							admin_url( 'admin-post.php' )
						),
						'wcce_download_default'
					);
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $email->get_title() ); ?></strong>
							<?php if ( $email->get_description() ) : ?>
								<br><span class="description"><?php echo esc_html( $email->get_description() ); ?></span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $email->id ); ?></code></td>
						<td>
							<?php if ( $has_custom ) : ?>
								<span class="wcce-badge wcce-badge--custom"><?php esc_html_e( 'Personalizado', 'wc-custom-emails' ); ?></span>
								<?php if ( ! empty( $template_meta['size'] ) ) : ?>
									<br><span class="description"><?php echo esc_html( size_format( $template_meta['size'] ) ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wcce-badge wcce-badge--default"><?php esc_html_e( 'Estándar WC', 'wc-custom-emails' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $placeholders ) ) : ?>
								<details class="wcce-placeholders">
									<summary><?php echo esc_html( sprintf( _n( '%d variable', '%d variables', count( $placeholders ), 'wc-custom-emails' ), count( $placeholders ) ) ); ?></summary>
									<ul>
										<?php foreach ( $placeholders as $placeholder ) : ?>
											<li><code><?php echo esc_html( $placeholder ); ?></code></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php else : ?>
								<span class="description">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="wcce-actions">
							<form method="post" action="<?php echo esc_url( $upload_action ); ?>" enctype="multipart/form-data" class="wcce-upload-form">
								<?php wp_nonce_field( 'wcce_upload_email' ); ?>
								<input type="hidden" name="action" value="wcce_upload_email">
								<input type="hidden" name="email_id" value="<?php echo esc_attr( $email->id ); ?>">
								<input type="file" name="email_html" accept=".html,.htm" required>
								<button type="submit" class="button button-primary">
									<?php esc_html_e( 'Subir HTML', 'wc-custom-emails' ); ?>
								</button>
							</form>

							<a href="<?php echo esc_url( $download_url ); ?>" class="button">
								<?php esc_html_e( 'Descargar estándar', 'wc-custom-emails' ); ?>
							</a>

							<?php if ( $has_custom ) : ?>
								<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( '¿Eliminar la plantilla personalizada y volver al estándar de WooCommerce?', 'wc-custom-emails' ) ); ?>');">
									<?php esc_html_e( 'Restaurar estándar', 'wc-custom-emails' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="wcce-help-box">
			<h2><?php esc_html_e( 'Cómo usar las plantillas', 'wc-custom-emails' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Descarga la plantilla estándar del email que quieres personalizar.', 'wc-custom-emails' ); ?></li>
				<li><?php esc_html_e( 'Edita el HTML y conserva los placeholders (ej: {order_number}, {site_title}, {order_details}).', 'wc-custom-emails' ); ?></li>
				<li><?php esc_html_e( 'Sube el archivo .html modificado. El plugin lo usará automáticamente al enviar ese correo.', 'wc-custom-emails' ); ?></li>
				<li><?php esc_html_e( 'Usa el ID exacto del email al subir la plantilla (ej: new_order, customer_completed_order).', 'wc-custom-emails' ); ?></li>
			</ol>
			<p class="description">
				<?php
				printf(
					/* translators: %s: storage directory path */
					esc_html__( 'Las plantillas se guardan en: %s', 'wc-custom-emails' ),
					esc_html( WCCE_Storage::get_directory() )
				);
				?>
			</p>
		</div>
	<?php endif; ?>
</div>
