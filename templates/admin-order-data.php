<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** @var string[] $bpost_meta */
?>
	<h4><?php echo bpost__( 'bpost shipping details' ); ?></h4>

	<div>
		<?php
		foreach ( $bpost_meta as $key => $value ) {
			if ( $key !== 'status' ) {
				echo '<p><strong>' . $value['translation'] . ':</strong> <span id="bpost-order-meta-' . $key . '">' . $value['value'] . '</span></p>';
			}
		}
		?>
	</div>

<?php if ( ! empty( $bpost_meta['status'] ) ) {
	$value = $bpost_meta['status'];
	?>
	<p>
		<?php echo '<strong>', $value['translation'], ':</strong> <span id="bpost-order-meta-status">', $value['value'], '</span>'; ?>
		<button type="button" class="button bpost-refresh-box-status-action">
			<?php echo bpost__( 'Refresh' ); ?>
		</button>
	</p>
<?php } ?>