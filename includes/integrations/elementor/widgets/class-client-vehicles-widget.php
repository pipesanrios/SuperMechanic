<?php
/**
 * Client vehicles Elementor widget.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Client vehicles shortcode wrapper for Elementor.
 */
class Client_Vehicles_Widget extends Widget_Base {
	public function get_name() {
		return 'sm-client-vehicles';
	}

	public function get_title() {
		return __( 'SM Client Vehicles', 'super-mechanic' );
	}

	public function get_icon() {
		return 'eicon-post-list';
	}

	public function get_categories() {
		return array( 'general' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'super-mechanic' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'super-mechanic' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => __( 'Optional title', 'super-mechanic' ),
				'default'     => '',
			)
		);

		$this->add_control(
			'business_id',
			array(
				'label'   => __( 'Business ID', 'super-mechanic' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
				'min'     => 0,
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Limit', 'super-mechanic' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
				'min'     => 0,
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( ! empty( $settings['title'] ) ) {
			echo '<h3>' . esc_html( (string) $settings['title'] ) . '</h3>';
		}

		echo do_shortcode( $this->build_shortcode( 'sm_client_vehicles', $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	protected function build_shortcode( $tag, array $settings ) {
		$attrs = array();
		if ( ! empty( $settings['business_id'] ) ) {
			$attrs[] = 'business_id="' . absint( $settings['business_id'] ) . '"';
		}
		if ( ! empty( $settings['limit'] ) ) {
			$attrs[] = 'limit="' . absint( $settings['limit'] ) . '"';
		}

		return '[' . sanitize_key( $tag ) . ( empty( $attrs ) ? '' : ' ' . implode( ' ', $attrs ) ) . ']';
	}
}

