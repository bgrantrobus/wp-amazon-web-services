<div class="aws-content aws-settings">
	<form id="aws-settings" method="post"> <!-- FIXME Shouldn't this have an explict action? -->
		<?php wp_nonce_field( 'aws-save-settings' ) ?>
		<input type="hidden" name="action" value="save" />

		<?php
		/*
		 * Credential Test Result
		 */
		?>
		<?php
			if ( isset( $_POST['action'] ) && 'test_credentials' == $_POST['action'] ) {

				$err_msg = $this->get_error_message( 'test_credentials_failed' );
				$err_data = $this->get_error_data( 'test_credentials_failed' );
				if ( $err_msg ) {
				?>
					<div class="aws-notice notice notice-error">
						<p>
							<strong>
								<?php printf( __( 'Credentials test failed: %s', 'amazon-web-services' ), $err_msg ); ?>
							</strong>
						</p>
						<p style="display: none;">
								<?php echo $err_data; ?>
						</p>
					</div>
				<?php
				}

				$success_msg = $this->get_error_message( 'test_credentials_success' );
				if ( $success_msg ) {
				?>
					<div class="aws-notice notice notice-success">
						<p>
							<strong>
								<?php printf( __( 'Successfully authenticated as: %s', 'amazon-web-services' ), $success_msg ); ?>
							</strong>
						</p>
					</div>
				<?php
				}
			}
		?>


		<?php
		/*
		 * Settings Saved Notification
		 */
		?>

		<?php if ( isset( $_POST['action'] ) && 'save' == $_POST['action'] ) : ?>
			<div class="aws-updated updated">
				<p><strong>Settings saved.</strong></p>
			</div>
		<?php endif; ?>


		<?php
		/*
		 * Sloppy Configuration Warnings
		 */
		?>

		<?php if ( 'wp-config' != $this->get_credentials_source()
							&& $this->are_key_constants_set() ) { ?>
		<div class="notice notice-warning" style="display: ;">
			<p>
				<strong>
					<?php
						_e( 'There are AWS keys defined in your wp-config.php, but'
								.' they are not currently being used.'
								.'	You should consider removing them.',
								'amazon-web-services' );
					?>
				</strong>
			</p>
		</div>
		<?php } ?>

		<?php if ( 'wp-config' == $this->get_credentials_source() && ! $this->are_key_constants_set() ) { ?>
		<div class="notice notice-warning" style="display: ;">
			<p>
				<strong>
					<?php
						_e( 'Configured to use AWS keys from wp-config.php, '
								.' but there are no keys defined in wp-config.php.',
								'amazon-web-services' );
					?>
				</strong>
			</p>
		</div>
		<?php } ?>

		<?php if ( 'wp-database' != $this->get_credentials_source() && $this->are_db_keys_set() ) { ?>
		<div class="notice notice-warning" style="display: ;">
			<p>
				<strong>
					<?php
						_e( 'There are AWS keys defined in your WordPress database, but'
								.' they are not currently being used.'
								.'	You should consider removing them.',
								'amazon-web-services' );
					?>
				</strong>
			</p>
		</div>
		<?php } ?>

		<?php if ( 'wp-database' == $this->get_credentials_source() && ! $this->are_db_keys_set() ) { ?>
		<div class="notice notice-warning" style="display: ;">
			<p>
				<strong>
					<?php
						_e( 'Configured to use AWS keys from the WordPress database,'
								.' but there are no keys defined in the database.',
								'amazon-web-services' );
					?>
				</strong>
			</p>
		</div>
		<?php } ?>


		<?php
		/*
		 * Credential Source Selection and Configuration (if storing in WP DB)
		 */
		?>

		<h3 class="title">
			<?php
				_e( 'Credentials', 'amazon-web-services' );
			?>
		</h3>
		<p>
			<?php
				_e( 'When you interact with AWS, you use AWS security credentials to'
						.' verify who you are and whether you have permission to access the'
						.' resources that you are requesting. In other words, security'
						.' credentials are used to authenticate and authorize calls that'
						.' you make to AWS.',
						'amazon-web-services' );
			?>
		</p>

		<p>
			<?php
				printf( __( 'If you don&#8217;t have an Amazon Web Services account'
						.' yet, you need to <a href="%s">sign up</a>.',
						'amazon-web-services' ),
					'http://aws.amazon.com' );

				printf( __( '  Once you have an account, you&#8217;ll need to get'
						.' security credentials in the form of access keys (access key ID'
						.' and secret access key).  For help, see <a href="%s">How Do I '
						.' Get Security Credentials?</a>',
						'amazon-web-services' ),
					'http://docs.aws.amazon.com/general/latest/gr/getting-aws-sec-creds.html' );
				?>
		</p>

		<p>
			<?php
				printf( __( 'Alternatively, if you are running WordPress on Amazon EC2'
						.' or Elastic Beanstalk, then the use of IAM roles is strongly'
						.' recommended.  See <a href="%s">IAM Roles (Delegation and'
						.' Federation)</a> for more information.',
						'amazon-web-services' ),
					'http://docs.aws.amazon.com/IAM/latest/UserGuide/roles-toplevel.html' );
				?>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label>
							<input type="radio" name="credentials_source" value="" <?php checked( '', $this->get_credentials_source() ); ?> />
							<?php _e( 'Environment', 'amazon-web-services' ); ?>
						</label>
					</th>
					<td>
						<?php _e( 'Obtain credentials from the environment.  This is the default.', 'amazon-web-services' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label>
							<input type="radio" name="credentials_source" value="aws-instance-roles" <?php checked( 'aws-instance-roles', $this->get_credentials_source() ); ?> />
							<?php _e( 'IAM Roles', 'amazon-web-services' ); ?>
						</label>
					</th>
					<td>
						<?php
							printf( __( 'Using IAM roles is the preferred technique for'
									.' providing credentials to applications running on Amazon'
									.' EC2.  For more information, see <a href="%s">IAM Roles for'
									.' Amazon EC2</a>.',
									'amazon-web-services' ),
								'http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/iam-roles-for-amazon-ec2.html' );
						?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label>
							<input type="radio" name="credentials_source" value="wp-config" <?php checked( 'wp-config', $this->get_credentials_source() ); ?> />
							<?php _e( 'WordPress Config', 'amazon-web-services' ); ?>
						</label>
					</th>
					<td>
						<?php _e( 'Credentials are defined in wp-config.php as follows:', 'amazon-web-services'); ?>
						<pre style="margin-bottom: 0;">define( 'AWS_ACCESS_KEY_ID', '**MY_KEY**' );
define( 'AWS_SECRET_ACCESS_KEY', '**MY_SECRET**' );</pre>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label>
							<input id="aws-db-choice" type="radio" name="credentials_source" value="wp-database" <?php checked( 'wp-database', $this->get_credentials_source() ); ?> />
							<?php _e( 'WordPress Database', 'amazon-web-services' ); ?>
						</label>
					</th>
					<td>
						<?php _e( 'Store credentials in the database.  For security reasons, <em><strong>this is not recommended</strong></em>.', 'amazon-web-services' ); ?>
					</td>
				</tr>
				<tr class="aws-db-setting" style="display: none;">
					<th scope="row">
						<?php _e( 'Access Key ID:', 'amazon-web-services' ); ?>
					</th>
					<td>
						<input type="text" name="access_key_id" value="<?php echo $this->get_db_access_key_id() // xss ok; ?>" size="50" autocomplete="off" />
					</td>
				</tr>
				<tr class="aws-db-setting" style="display: none;">
					<th scope="row">
						<?php _e( 'Secret Access Key:', 'amazon-web-services' ); ?>
					</th>
					<td>
						<input type="text" name="secret_access_key" value="<?php echo $this->get_db_secret_access_key() ? '-- not shown --' : ''; // xss ok ?>" size="50" autocomplete="off" />
					</td>
				</tr>
			</tbody>
		</table>


		<?php
		/*
		 * You gotta submit to commit! (And clear + submit to remove keys from DB.)
		 */
		?>

		<p class="submit">
			<span>
				<?php submit_button( __( 'Save Changes', 'amazon-web-services' ), 'primary', 'submit', false ); ?>
			</span>
			<span>
				<?php 
					if ( $this->get_db_access_key_id() || $this->get_db_secret_access_key() ) {
						submit_button( __( 'Remove Keys', 'amazon-web-services' ), 'remove-keys', 'remove-keys', false);
					}
				?>
			</span>
		</p>
	</form>


	<?php
	/*
	 * Test Configuration (Does not save, just triggers the test)
	 */
	?>
	<form method="post"> <!-- FIXME Shouldn't this have an explict action? -->
		<?php wp_nonce_field( 'aws-test-settings' ) ?>
		<input type="hidden" name="action" value="test_credentials" />

		<h3>Test Credentials</h3>

		<p>
			<?php
				_e( 'Confirm the credential configuration works by testing it.'
						.'  Go ahead.  I dare you.  (Save any changes by clicking '
						.' &#8217;Save Changes&#8217; first.)',
						'amazon-web-services' );
			?>
		</p>

		<?php submit_button( 'Test Credentials' ); ?>
	</form>

</div>
