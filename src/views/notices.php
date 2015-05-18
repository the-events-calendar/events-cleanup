<?php if ( ! empty( $plugin->warnings ) ): ?>
	<?php foreach ( $plugin->warnings as $warning ): ?>
		<div class="error"> <p>
				<?php echo $warning ?>
			</p> </div>
	<?php endforeach ?>
<?php endif ?>

<?php if ( ! empty( $plugin->notices ) ): ?>
	<?php foreach ( $plugin->notices as $notice ): ?>
		<div class="updated"> <p>
				<?php echo $notice ?>
			</p> </div>
	<?php endforeach ?>
<?php endif ?>