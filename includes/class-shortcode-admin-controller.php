<?php
/**
 * Shortcode admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the admin shortcode catalog page.
 */
class Shortcode_Admin_Controller {
	/**
	 * Render the shortcode catalog page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'super-mechanic' ) );
		}

		$groups         = $this->get_grouped_shortcodes();
		$total_detected = count( $groups['client'] ) + count( $groups['mechanic'] ) + count( $groups['general'] );

		echo '<div class="wrap sm-admin-shell sm-shortcode-catalog">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Catálogo de shortcodes', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Consulta los shortcodes activos del plugin, revisa su uso recomendado y copia ejemplos sin tocar su lógica existente.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<div class="sm-page-actions">';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html( sprintf( _n( '%d shortcode activo', '%d shortcodes activos', $total_detected, 'super-mechanic' ), $total_detected ) ) . '</span>';
		echo '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'Panel informativo', 'super-mechanic' ) . '</span>';
		echo '</div>';
		echo '</div>';

		echo '<section class="sm-section">';
		echo '<div class="sm-grid sm-grid-cards">';
		echo $this->render_kpi_card( __( 'Cliente', 'super-mechanic' ), count( $groups['client'] ), __( 'Shortcodes activos para Client Portal y áreas autenticadas.', 'super-mechanic' ) );
		echo $this->render_kpi_card( __( 'Mecánico', 'super-mechanic' ), count( $groups['mechanic'] ), __( 'Hoy no hay shortcodes mecánicos activos en el bootstrap real.', 'super-mechanic' ) );
		echo $this->render_kpi_card( __( 'General', 'super-mechanic' ), count( $groups['general'] ), __( 'Espacio reservado para shortcodes públicos o neutros futuros.', 'super-mechanic' ) );
		echo '</div>';
		echo '</section>';

		echo '<section class="sm-section">';
		echo '<div class="sm-card sm-card-muted">';
		echo '<div class="sm-card-header">';
		echo '<h2 class="sm-card-title">' . esc_html__( 'Criterio del catálogo', 'super-mechanic' ) . '</h2>';
		echo '<span class="sm-badge sm-badge-success">' . esc_html__( 'Fuente real', 'super-mechanic' ) . '</span>';
		echo '</div>';
		echo '<p>' . esc_html__( 'Este panel documenta únicamente shortcodes activos detectados en las clases reales del plugin y agrupados por contexto recomendado de uso.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '</section>';

		$this->render_group_section(
			'client',
			__( 'Shortcodes de cliente', 'super-mechanic' ),
			__( 'Pensados para Client Portal, páginas privadas y vistas con ownership validado.', 'super-mechanic' ),
			$groups['client']
		);
		$this->render_group_section(
			'mechanic',
			__( 'Shortcodes de mecánico', 'super-mechanic' ),
			__( 'Reservado para shortcodes operativos de mecánico si en una fase futura se cablean al runtime real.', 'super-mechanic' ),
			$groups['mechanic']
		);
		$this->render_group_section(
			'general',
			__( 'Shortcodes generales o públicos', 'super-mechanic' ),
			__( 'Reservado para shortcodes neutros o públicos cuando existan en el bootstrap activo.', 'super-mechanic' ),
			$groups['general']
		);

		echo '</div>';
	}

	/**
	 * Render a group section.
	 *
	 * @param string                                  $group_key Group key.
	 * @param string                                  $title     Section title.
	 * @param string                                  $subtitle  Section subtitle.
	 * @param array<int, array<string, string|array>> $items     Group items.
	 * @return void
	 */
	protected function render_group_section( $group_key, $title, $subtitle, array $items ) {
		echo '<section class="sm-section sm-shortcode-group sm-shortcode-group-' . esc_attr( $group_key ) . '">';
		echo '<div class="sm-section-heading">';
		echo '<div>';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<p class="sm-admin-subtitle">' . esc_html( $subtitle ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-neutral">' . esc_html( sprintf( _n( '%d elemento', '%d elementos', count( $items ), 'super-mechanic' ), count( $items ) ) ) . '</span>';
		echo '</div>';

		if ( empty( $items ) ) {
			echo '<div class="sm-empty">';
			echo '<strong>' . esc_html__( 'Sin shortcodes activos en este grupo.', 'super-mechanic' ) . '</strong> ';
			echo esc_html__( 'El catálogo evita inventar entradas que no estén cableadas en el bootstrap real.', 'super-mechanic' );
			echo '</div>';
			echo '</section>';
			return;
		}

		echo '<div class="sm-grid sm-grid-two">';
		foreach ( $items as $item ) {
			echo $this->render_shortcode_card( $item );
		}
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render a shortcode card.
	 *
	 * @param array<string, string|array> $item Shortcode metadata.
	 * @return string
	 */
	protected function render_shortcode_card( array $item ) {
		$tag         = isset( $item['tag'] ) ? (string) $item['tag'] : '';
		$code        = '[' . $tag . ']';
		$example     = isset( $item['example'] ) ? (string) $item['example'] : $code;
		$copy_id     = 'sm-shortcode-copy-' . sanitize_html_class( $tag );
		$feedback_id = 'sm-shortcode-feedback-' . sanitize_html_class( $tag );
		$params      = isset( $item['parameters'] ) && is_array( $item['parameters'] ) ? $item['parameters'] : array();

		ob_start();
		echo '<article class="sm-card sm-shortcode-card">';
		echo '<div class="sm-card-header">';
		echo '<div>';
		echo '<h3 class="sm-card-title"><code>' . esc_html( $code ) . '</code></h3>';
		echo '<p class="sm-list-meta">' . esc_html( isset( $item['source'] ) ? (string) $item['source'] : '' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html( isset( $item['recommended_context'] ) ? (string) $item['recommended_context'] : '' ) . '</span>';
		echo '</div>';

		echo '<p>' . esc_html( isset( $item['description'] ) ? (string) $item['description'] : '' ) . '</p>';

		echo '<div class="sm-shortcode-field-group">';
		echo '<label class="sm-inline-label" for="' . esc_attr( $copy_id ) . '">' . esc_html__( 'Ejemplo de uso', 'super-mechanic' ) . '</label>';
		echo '<textarea id="' . esc_attr( $copy_id ) . '" class="sm-shortcode-input" readonly rows="2">' . esc_textarea( $example ) . '</textarea>';
		echo '<div class="sm-card-copy">';
		echo '<button type="button" class="button button-secondary sm-button-secondary sm-copy-shortcode" data-copy-target="' . esc_attr( $copy_id ) . '" data-feedback-target="' . esc_attr( $feedback_id ) . '" data-default-label="' . esc_attr__( 'Copiar shortcode', 'super-mechanic' ) . '" data-success-label="' . esc_attr__( 'Copiado', 'super-mechanic' ) . '">' . esc_html__( 'Copiar shortcode', 'super-mechanic' ) . '</button>';
		echo '<span id="' . esc_attr( $feedback_id ) . '" class="sm-shortcode-feedback" aria-live="polite"></span>';
		echo '</div>';
		echo '</div>';

		echo '<table class="sm-table sm-shortcode-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Descripción', 'super-mechanic' ) . '</th><td>' . esc_html( isset( $item['description'] ) ? (string) $item['description'] : '' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Parámetros', 'super-mechanic' ) . '</th><td>' . wp_kses_post( $this->render_parameters_html( $params ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Contexto recomendado', 'super-mechanic' ) . '</th><td>' . esc_html( isset( $item['recommended_context'] ) ? (string) $item['recommended_context'] : '' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Dónde usarlo', 'super-mechanic' ) . '</th><td>' . esc_html( isset( $item['usage_scope'] ) ? (string) $item['usage_scope'] : '' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '</article>';

		return (string) ob_get_clean();
	}

	/**
	 * Render parameter list HTML.
	 *
	 * @param array<int, string> $parameters Parameter descriptions.
	 * @return string
	 */
	protected function render_parameters_html( array $parameters ) {
		if ( empty( $parameters ) ) {
			return esc_html__( 'Sin parámetros.', 'super-mechanic' );
		}

		$html = '<ul class="sm-shortcode-params">';
		foreach ( $parameters as $parameter ) {
			$html .= '<li>' . esc_html( (string) $parameter ) . '</li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Render KPI card.
	 *
	 * @param string $label    Card label.
	 * @param int    $value    Count value.
	 * @param string $footnote Footnote.
	 * @return string
	 */
	protected function render_kpi_card( $label, $value, $footnote ) {
		ob_start();
		echo '<article class="sm-card sm-kpi-card">';
		echo '<span class="sm-kpi-label">' . esc_html( $label ) . '</span>';
		echo '<span class="sm-kpi-value">' . esc_html( number_format_i18n( $value ) ) . '</span>';
		echo '<p class="sm-kpi-footnote">' . esc_html( $footnote ) . '</p>';
		echo '</article>';

		return (string) ob_get_clean();
	}

	/**
	 * Get grouped active shortcode metadata.
	 *
	 * @return array<string, array<int, array<string, string|array>>>
	 */
	protected function get_grouped_shortcodes() {
		$groups = array(
			'client'   => array(),
			'mechanic' => array(),
			'general'  => array(),
		);

		foreach ( $this->get_shortcode_catalog() as $item ) {
			$tag = isset( $item['tag'] ) ? (string) $item['tag'] : '';
			if ( '' === $tag || ! shortcode_exists( $tag ) ) {
				continue;
			}

			$group = isset( $item['group'] ) ? (string) $item['group'] : 'general';
			if ( ! isset( $groups[ $group ] ) ) {
				$group = 'general';
			}

			$groups[ $group ][] = $item;
		}

		return $groups;
	}

	/**
	 * Get shortcode catalog metadata.
	 *
	 * @return array<int, array<string, string|array>>
	 */
	protected function get_shortcode_catalog() {
		return array(
			array(
				'tag'                 => 'sm_client_dashboard',
				'group'               => 'client',
				'description'         => __( 'Muestra el panel principal del cliente con resumen, actividad y accesos a sus recursos.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_dashboard]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Página principal del Client Portal o área privada autenticada.', 'super-mechanic' ),
				'source'              => 'includes/dashboard/class-client-dashboard-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_vehicles',
				'group'               => 'client',
				'description'         => __( 'Lista los vehículos asociados al cliente autenticado.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_vehicles]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Sección privada de vehículos dentro del Client Portal.', 'super-mechanic' ),
				'source'              => 'includes/dashboard/class-client-dashboard-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_processes',
				'group'               => 'client',
				'description'         => __( 'Renderiza los procesos accesibles del cliente autenticado.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_processes]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Listado privado de procesos del cliente.', 'super-mechanic' ),
				'source'              => 'includes/dashboard/class-client-dashboard-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_process_documents',
				'group'               => 'client',
				'description'         => __( 'Muestra los documentos visibles de un proceso con descargas seguras.', 'super-mechanic' ),
				'parameters'          => array(
					__( '`process_id` (opcional): ID del proceso que se quiere mostrar.', 'super-mechanic' ),
				),
				'example'             => '[sm_client_process_documents process_id="123"]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Detalle privado de proceso o landing interna del Client Portal.', 'super-mechanic' ),
				'source'              => 'includes/attachments/class-client-attachment-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_process_timeline',
				'group'               => 'client',
				'description'         => __( 'Renderiza la timeline visible al cliente para un proceso concreto.', 'super-mechanic' ),
				'parameters'          => array(
					__( '`process_id` (opcional): ID del proceso que se quiere mostrar.', 'super-mechanic' ),
				),
				'example'             => '[sm_client_process_timeline process_id="123"]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Vistas privadas de seguimiento de proceso.', 'super-mechanic' ),
				'source'              => 'includes/attachments/class-client-attachment-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_quotes',
				'group'               => 'client',
				'description'         => __( 'Lista las cotizaciones del cliente autenticado con accesos a detalle y PDF.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_quotes]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Sección privada de cotizaciones del Client Portal.', 'super-mechanic' ),
				'source'              => 'includes/quotes/class-client-quote-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_quote_detail',
				'group'               => 'client',
				'description'         => __( 'Muestra el detalle completo de una cotización y sus acciones disponibles.', 'super-mechanic' ),
				'parameters'          => array(
					__( '`id` (opcional): ID de la cotización. Si no se envía, puede resolverse por `quote_id` en query string.', 'super-mechanic' ),
				),
				'example'             => '[sm_client_quote_detail id="45"]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Página privada de detalle de cotización.', 'super-mechanic' ),
				'source'              => 'includes/quotes/class-client-quote-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_quote_action',
				'group'               => 'client',
				'description'         => __( 'Procesa acciones de aprobación o rechazo de cotizaciones desde formularios del cliente.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_quote_action]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Misma página donde exista el formulario de acción de quote.', 'super-mechanic' ),
				'source'              => 'includes/quotes/class-client-quote-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_invoices',
				'group'               => 'client',
				'description'         => __( 'Lista las facturas del cliente con estado financiero agregado.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_invoices]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Sección privada de facturas dentro del Client Portal.', 'super-mechanic' ),
				'source'              => 'includes/invoices/class-client-invoice-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_invoice_detail',
				'group'               => 'client',
				'description'         => __( 'Renderiza el detalle de una factura, sus ítems, pagos y documentos asociados.', 'super-mechanic' ),
				'parameters'          => array(
					__( '`id` (opcional): ID de la factura. Si no se envía, puede resolverse por `invoice_id` en query string.', 'super-mechanic' ),
				),
				'example'             => '[sm_client_invoice_detail id="78"]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Página privada de detalle de factura.', 'super-mechanic' ),
				'source'              => 'includes/invoices/class-client-invoice-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_process_comments',
				'group'               => 'client',
				'description'         => __( 'Muestra los comentarios visibles para el cliente en un proceso concreto.', 'super-mechanic' ),
				'parameters'          => array(
					__( '`process_id` (opcional): ID del proceso que se quiere mostrar.', 'super-mechanic' ),
				),
				'example'             => '[sm_client_process_comments process_id="123"]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Detalle privado del proceso dentro del Client Portal.', 'super-mechanic' ),
				'source'              => 'includes/communication/class-client-comment-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_process_comment_form',
				'group'               => 'client',
				'description'         => __( 'Renderiza el formulario para que el cliente envíe mensajes sobre un proceso.', 'super-mechanic' ),
				'parameters'          => array(
					__( '`process_id` (opcional): ID del proceso al que se asociará el comentario.', 'super-mechanic' ),
				),
				'example'             => '[sm_client_process_comment_form process_id="123"]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Página privada del proceso junto al historial de comentarios.', 'super-mechanic' ),
				'source'              => 'includes/communication/class-client-comment-shortcodes.php',
			),
			array(
				'tag'                 => 'sm_client_notifications',
				'group'               => 'client',
				'description'         => __( 'Muestra las notificaciones del cliente autenticado.', 'super-mechanic' ),
				'parameters'          => array(),
				'example'             => '[sm_client_notifications]',
				'recommended_context' => __( 'Cliente', 'super-mechanic' ),
				'usage_scope'         => __( 'Centro privado de notificaciones o dashboard cliente.', 'super-mechanic' ),
				'source'              => 'includes/communication/class-client-comment-shortcodes.php',
			),
		);
	}
}
