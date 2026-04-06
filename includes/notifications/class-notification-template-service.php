<?php
/**
 * Notification template service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Provides base notification templates and rendering.
 */
class Notification_Template_Service {
	/**
	 * Supported template map.
	 *
	 * @var array<string,array<string,string>>
	 */
	protected $templates = array(
		'user_assigned_to_business' => array(
			'subject' => 'Assigned to {{business_name}}',
			'body'    => "Hello {{user_name}},\n\nYou were assigned to {{business_name}} with role {{role}}.\n\nStatus: {{status}}\n",
		),
		'user_transferred' => array(
			'subject' => 'Transferred to {{business_name}}',
			'body'    => "Hello {{user_name}},\n\nYou were transferred to {{business_name}}.\n\nRole: {{role}}\nStatus: {{status}}\nMode: {{mode}}\n",
		),
		'membership_created' => array(
			'subject' => 'Membership created in {{business_name}}',
			'body'    => "Hello {{user_name}},\n\nA new membership was created in {{business_name}}.\n\nRole: {{role}}\nStatus: {{status}}\n",
		),
		'membership_updated' => array(
			'subject' => 'Membership updated in {{business_name}}',
			'body'    => "Hello {{user_name}},\n\nYour membership in {{business_name}} was updated.\n\nRole: {{role}}\nStatus: {{status}}\n",
		),
		'overdue_alert_detected' => array(
			'subject' => 'Overdue alert detected in {{business_name}}',
			'body'    => "Hello {{user_name}},\n\nAn overdue operational alert was detected in {{business_name}}.\n\nOverdue tasks: {{value}}\nThreshold: {{threshold}}\n",
		),
		'critical_signal_detected' => array(
			'subject' => 'Critical operational signal detected in {{business_name}}',
			'body'    => "Hello {{user_name}},\n\nA critical operational signal was detected in {{business_name}}.\n\nCritical signals: {{value}}\nThreshold: {{threshold}}\n",
		),
	);

	/**
	 * Get template by notification type.
	 *
	 * @param string $type Notification type.
	 * @return array<string,string>|null
	 */
	public function get_template( $type ) {
		$type = sanitize_key( (string) $type );
		if ( '' === $type || ! isset( $this->templates[ $type ] ) ) {
			return null;
		}

		$template = $this->templates[ $type ];
		if ( ! is_array( $template ) || ! isset( $template['subject'], $template['body'] ) ) {
			return null;
		}

		return array(
			'subject' => (string) $template['subject'],
			'body'    => (string) $template['body'],
		);
	}

	/**
	 * Render template with scalar placeholder values.
	 *
	 * @param string               $template Template body/subject.
	 * @param array<string,mixed>  $data Dynamic data.
	 * @return string
	 */
	public function render_template( $template, array $data ) {
		$template = (string) $template;
		if ( '' === $template ) {
			return '';
		}

		$replacements = array();
		foreach ( $data as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$replacements[ '{{' . $key . '}}' ] = sanitize_text_field( (string) $value );
			}
		}

		return strtr( $template, $replacements );
	}
}
