<?php

class cf2agile_API_ActiveCollab {

	private $org_name = null;
	private $app_name = null;
	private $username = null;
	private $password = null;
	private $self_url = null;

	private $_access_token = false;
	private static $_instance = null;

	/**
	 * @return cf2agile_API_ActiveCollab
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Set ActiveCollab Access data
	 */
	private function __construct() {
		$this->org_name = get_option( 'cf2agile__activecollab__org_name' );
		$this->app_name = get_option( 'cf2agile__activecollab__app_name' );
		$this->username = get_option( 'cf2agile__activecollab__username' );
		$this->password = get_option( 'cf2agile__activecollab__password' );
		$this->self_url = get_option( 'cf2agile__activecollab__self_url' );
	}

	/**
	 *
	 *
	 * @param string $action
	 * @return string
	 */
	private function _get_action_url( $action ) {
		$self_url = ( substr( $this->self_url, -1 ) === '/' ) ? substr( $this->self_url, 0, -1 ) : $this->self_url;
		$action = ( substr( $action, 0, 1 ) === '/' ) ? substr( $action, 1 ) : $action;

		return "{$self_url}/api/v1/{$action}";
	}

	/**
	 * Get access token
	 *
	 * @return string|false
	 */
	private function issue_token() {
		$url = $this->_get_action_url( '/issue-token' );

		if ( false !== $this->_access_token ) {
			return $this->_access_token;
		}

		$request = wp_remote_post( $url, array(
			'body' => array(
				'username' => $this->username,
				'password' => $this->password,
				'client_name' => $this->app_name,
				'client_vendor' => $this->org_name,
				)
		) );

		if ( ! is_wp_error( $request ) && $request['response']['code'] === 200 ) {
			$response = json_decode( $request['body'], true );

			if ( isset( $response['is_ok'], $response['token'] ) && $response['is_ok'] === true ) {
				return $this->_access_token = $response['token'];
			}
		}

		return false;
	}

	/**
	 * Create Project in ActiveCollab
	 *
	 * @param string $project_name
	 * @param string $company_name
	 * @param float $budget
	 * @param string $customer_email
	 * @return int|false
	 */
	public function create_project( $project_name, $company_name = null, $budget = null, $customer_email = null ) {
		$url = $this->_get_action_url( '/projects' );
		$access_token = $this->issue_token();
		$company_id = $members_id = false;

		if ( ! empty( $company_name ) ) {
			$company_id = $this->get_or_create_company( $company_name );
		}

		$request = wp_remote_post( $url, array(
			'headers' => array( 'X-Angie-AuthApiToken' => $access_token ),
			'body' => array(
				'name' => $project_name,
				'budget' => $budget,
				'members' => array( $members_id ),
				'company_id' => $company_id,
			),
		) );

		if ( is_wp_error( $request ) ) {
			return false;
		}
		if ( 200 !== $request['response']['code'] ) {
			return $request['response']['message'];
		}

		$response = json_decode( $request['body'], true );
		$project_id = isset( $response['single'] ) ? $response['single']['id'] : false;

		if ( false !== $project_id ) {
			$this->invite_member( $customer_email, $project_id, $company_id );
		}

		return $project_id;
	}

	/**
	 * Create note for project in ActiveCollab
	 *
	 * @param int $project_id
	 * @param string $title
	 * @param string $text
	 * @return int|false
	 */
	public function add_note_in_project( $project_id, $title, $text ) {
		$url = $this->_get_action_url( "/projects/{$project_id}/notes" );
		$access_token = $this->issue_token();

		$request = wp_remote_post( $url, array(
			'headers' => array( 'X-Angie-AuthApiToken' => $access_token ),
			'body' => array( 'name' => $title, 'body' => $text ),
		) );

		if ( is_wp_error( $request ) ) {
			return false;
		}
		if ( 200 !== $request['response']['code'] ) {
			return $request['response']['message'];
		}

		$response = json_decode( $request['body'], true );

		return isset( $response['single'] ) ? $response['single']['id'] : false;
	}

	/**
	 * Get company id or create company and return company id
	 *
	 * @param string $company_name
	 * @return int|false
	 */
	public function get_or_create_company( $company_name ) {
		$url = $this->_get_action_url( '/companies' );
		$access_token = $this->issue_token();

		$request = wp_remote_get( $url, array( 'headers' => array( 'X-Angie-AuthApiToken' => $access_token ) ) );

		if ( is_wp_error( $request ) ) {
			return false;
		}
		if ( 200 !== $request['response']['code'] ) {
			return $request['response']['message'];
		}

		$companies = json_decode( $request['body'], true );

		if ( ! empty( $companies ) ) {
			foreach ( $companies as $company ) {
				if ( strtolower( $company_name ) === strtolower( $company['name'] ) ) {
					return $company['id'];
				}
			}
		}

		$request = wp_remote_post( $url, array(
			'headers' => array( 'X-Angie-AuthApiToken' => $access_token ),
			'body' => array( 'name' => sanitize_text_field( $company_name ) ),
		) );

		if ( is_wp_error( $request ) ) {
			return false;
		}
		if ( 200 !== $request['response']['code'] ) {
			return $request['response']['message'];
		}

		$response = json_decode( $request['body'], true );

		return isset( $response['single'] ) ? $response['single']['id'] : false;
	}

	/**
	 * Invite member to project
	 *
	 * @param string $customer_email
	 * @param int $project_id
	 * @param int $company_id
	 * @return array|false
	 */
	public function invite_member( $customer_email, $project_id = null, $company_id = null ) {
		$url = $this->_get_action_url( '/users' );
		$access_token = $this->issue_token();

		$request = wp_remote_get( $url, array( 'headers' => array( 'X-Angie-AuthApiToken' => $access_token ) ) );

		if ( is_wp_error( $request ) ) {
			return false;
		}
		if ( 200 !== $request['response']['code'] ) {
			return $request['response']['message'];
		}

		$users = json_decode( $request['body'], true );

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				if ( strtolower( $customer_email ) === strtolower( $user['email'] ) ) {
					$url = $this->_get_action_url( "/projects/{$project_id}/members" );

					return wp_remote_post( $url, array(
						'headers' => array( 'X-Angie-AuthApiToken' => $access_token ),
						'body' => array( $user['id'] ),
					) );
				}
			}
		}

		$url = $this->_get_action_url( '/users/invite/' );

		return wp_remote_post( $url, array(
			'headers' => array( 'X-Angie-AuthApiToken' => $access_token ),
			'body' => array(
				'role' => 'Client',
				'company_id' => $company_id,
				'project_ids' => array( $project_id ),
				'email_addresses' => array( $customer_email ),
			),
		) );
	}

}
