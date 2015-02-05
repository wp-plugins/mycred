<?php
if ( ! defined( 'myCRED_VERSION' ) ) exit;

/**
 * MarketPress Setup
 * @since 1.6
 * @version 1.0
 */
add_action( 'after_setup_theme', 'mycred_load_marketpress_reward', 80 );
if ( ! function_exists( 'mycred_load_marketpress_reward' ) ) :
	function mycred_load_marketpress_reward()
	{
		add_action( 'add_meta_boxes_product', 'mycred_markpress_add_product_metabox' );
		add_action( 'save_post',              'mycred_markpress_save_reward_settings' );
		add_action( 'mp_order_paid',          'mycred_markpress_payout_rewards' );
	}
endif;

/**
 * Add Reward Metabox
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_markpress_add_product_metabox' ) ) :
	function mycred_markpress_add_product_metabox()
	{
		add_meta_box(
			'mycred_markpress_sales_setup',
			mycred_label(),
			'mycred_markpress_product_metabox',
			'product',
			'side',
			'high'
		);
	}
endif;

/**
 * Product Metabox
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_markpress_product_metabox' ) ) :
	function mycred_markpress_product_metabox( $post )
	{
		if ( ! current_user_can( apply_filters( 'mycred_markpress_reward_cap', 'edit_others_posts' ) ) ) return;

		$types = mycred_get_types();
		$prefs = (array) get_post_meta( $post->ID, 'mycred_reward', true );

		foreach ( $types as $type => $label ) {
			if ( ! isset( $prefs[ $type ] ) )
				$prefs[ $type ] = '';
		}

		$count = 0;
		$cui = get_current_user_id();
		foreach ( $types as $type => $label ) {
			$count ++;
			$mycred = mycred( $type );
			if ( ! $mycred->can_edit_creds( $cui ) ) continue; ?>

		<p class="<?php if ( $count == 1 ) echo 'first'; ?>"><label for="mycred-reward-purchase-with-<?php echo $type; ?>"><input class="toggle-mycred-reward" data-id="<?php echo $type; ?>" <?php if ( $prefs[ $type ] != '' ) echo 'checked="checked"'; ?> type="checkbox" name="mycred_reward[<?php echo $type; ?>][use]" id="mycred-reward-purchase-with-<?php echo $type; ?>" value="<?php echo $prefs[ $type ]; ?>" /> <?php echo $mycred->template_tags_general( __( 'Reward with %plural%', 'mycred' ) ); ?></label></p>
		<div class="mycred-mark-wrap" id="reward-<?php echo $type; ?>" style="display:<?php if ( $prefs[ $type ] == '' ) echo 'none'; else echo 'block'; ?>">
			<label><?php echo $mycred->plural(); ?></label> <input type="text" size="8" name="mycred_reward[<?php echo $type; ?>][amount]" value="<?php echo $prefs[ $type ]; ?>" placeholder="<?php echo $mycred->zero(); ?>" />
		</div>
<?php
		} ?>

<script type="text/javascript">
jQuery(function($) {

	$( '.toggle-mycred-reward' ).click(function(){
		var target = $(this).attr( 'data-id' );
		$( '#reward-' + target ).toggle();
	});

});
</script>
<style type="text/css">
#mycred_markpress_sales_setup .inside { margin: 0; padding: 0; }
#mycred_markpress_sales_setup .inside > p { padding: 12px; margin: 0; border-top: 1px solid #ddd; }
#mycred_markpress_sales_setup .inside > p.first { border-top: none; }
#mycred_markpress_sales_setup .inside .mycred-mark-wrap { padding: 6px 12px; line-height: 27px; text-align: right; border-top: 1px solid #ddd; background-color: #F5F5F5; }
#mycred_markpress_sales_setup .inside .mycred-mark-wrap label { display: block; font-weight: bold; float: left; }
#mycred_markpress_sales_setup .inside .mycred-mark-wrap input { width: 50%; }
#mycred_markpress_sales_setup .inside .mycred-mark-wrap p { margin: 0; padding: 0 12px; font-style: italic; text-align: center; }
</style>
<?php
	}
endif;

/**
 * Save Reward Setup
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_markpress_save_reward_settings' ) ) :
	function mycred_markpress_save_reward_settings( $post_id )
	{
		if ( ! isset( $_POST['mycred_reward'] ) ) return;

		$new_settings = array();
		foreach ( $_POST['mycred_reward'] as $type => $prefs ) {

			$mycred = mycred( $type );
			if ( isset( $prefs['use'] ) )
				$new_settings[ $type ] = $mycred->number( $prefs['amount'] );

		}

		update_post_meta( $post_id, 'mycred_reward', $new_settings );
	}
endif;

/**
 * Payout Rewards
 * @since 1.6
 * @version 1.0
 */
if ( ! function_exists( 'mycred_markpress_payout_rewards' ) ) :
	function mycred_markpress_payout_rewards( $order )
	{
		// Payment info
		$payment_info = get_post_meta( $order->ID, 'mp_payment_info', true );
		if ( ! isset( $payment_info['gateway_private_name'] ) || ( $payment_info['gateway_private_name'] == 'myCRED' && apply_filters( 'mycred_marketpress_reward_mycred_payment', false ) === false ) )
			return;

		// Get buyer ID
		global $wpdb;

		$meta_id = 'mp_order_history';
		if ( is_multisite() ) {
			global $blog_id;
			$meta_id = 'mp_order_history_' . $blog_id;
		}

		// Get buyer
		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s", $meta_id, '%s:2:"id";i:' . $order->ID . ';%' ) );
		if ( $user_id === NULL && ! is_user_logged_in() ) return;
		elseif ( $user_id === NULL ) $user_id = get_current_user_id();

		// Get point types
		$types = mycred_get_types();

		// Loop
		foreach ( $types as $type => $label ) {

			// Load type
			$mycred = mycred( $type );

			// Check for exclusions
			if ( $mycred->exclude_user( $user_id ) ) continue;

			// Calculate reward
			$reward = $mycred->zero();
			foreach ( $order->mp_cart_info as $product_id => $variations ) {
				foreach ( $variations as $variation => $data ) {

					$prefs = (array) get_post_meta( (int) $product_id, 'mycred_reward', true );
					if ( isset( $prefs[ $type ] ) && $prefs[ $type ] != '' )
						$reward = ( $reward + ( $prefs[ $type ] * $data['quantity'] ) );

				}
			}

			// Award
			if ( $reward != $mycred->zero() ) {

				// Let others play with the reference and log entry
				$reference = apply_filters( 'mycred_marketpress_reward_reference', 'marketpress_reward', $order_id, $type );
				$log = apply_filters( 'mycred_marketpress_reward_log', '%plural% reward for store purchase', $order_id, $type );

				// Execute
				$mycred->add_creds(
					$reference,
					$order->user_id,
					$reward,
					$log,
					$order->ID,
					array( 'ref_type' => 'post' ),
					$type
				);

			}

		}
	}
endif;

?>