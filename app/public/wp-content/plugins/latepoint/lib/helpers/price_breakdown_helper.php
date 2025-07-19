<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class OsPriceBreakdownHelper {

	public static function output_price_breakdown($rows, $inline_styles = false) {
        $prev_heading = '';
		foreach ($rows['before_subtotal'] as $row) {
            $skip_heading = !empty($row['heading']) && $row['heading'] == $prev_heading;
			self::output_price_breakdown_row($row, $inline_styles, $skip_heading);
            $prev_heading = $row['heading'];
		}
		// if there is nothing between subtotal and total - don't show subtotal as it will be identical to total
		if (!empty($rows['after_subtotal'])) {
			if (!empty($rows['subtotal'])) {
				echo '<div class="subtotal-separator"></div>';
				self::output_price_breakdown_row($rows['subtotal'], $inline_styles);
			}
			foreach ($rows['after_subtotal'] as $row) {
				self::output_price_breakdown_row($row, $inline_styles);
			}
		}
		if (!empty($rows['total'])) {
			self::output_price_breakdown_row($rows['total'], $inline_styles);
		}
		if (!empty($rows['payments'])) {
			foreach ($rows['payments'] as $row) {
				self::output_price_breakdown_row($row, $inline_styles);
			}
		}
		if (!empty($rows['balance'])) {
			self::output_price_breakdown_row($rows['balance'], $inline_styles);
		}
	}

	public static function output_price_breakdown_row($row, $inline_styles = false, $skip_heading = false) {
		if (!empty($row['items'])) {
            if(!$skip_heading){
                if($inline_styles){
                    if (!empty($row['heading'])) echo '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 5px; margin-top: 10px;"><tr><td style="color: #788291;font-size: 11px;text-transform: uppercase;letter-spacing: 1px;font-weight: 600;">' . esc_html($row['heading']) . '</td><td style="width: 100%;"><div style="height: 1px;background-color: #f1f1f1;margin-left: 10px;"></div></td></tr></table>';
                } else {
                    if (!empty($row['heading'])) echo '<div class="summary-box-heading"><div class="sbh-item">' . esc_html($row['heading']) . '</div><div class="sbh-line"></div></div>';
                }
            }
			foreach ($row['items'] as $row_item) {
				self::output_price_breakdown_row($row_item, $inline_styles);
			}
		} else {
			$extra_class = '';
			$extra_css = '';
			if (isset($row['style']) && $row['style'] == 'strong') $extra_class .= ' spi-strong';
			if (isset($row['style']) && $row['style'] == 'total'){
				$extra_class .= ' spi-total';
				if($inline_styles) $extra_css = 'border-top: 3px solid #41444b;padding-top: 10px;margin-top: 10px;font-size: 16px;';
			}
			if (isset($row['type']) && $row['type'] == 'credit') $extra_class .= ' spi-positive';
			if (isset($row['style']) && $row['style'] == 'sub') $extra_class .= ' spi-sub';

			if ($inline_styles) { ?>
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 7px;<?php echo esc_attr($extra_css); ?>">
                    <tr>
                        <td style="text-align: left;">
							<?php echo esc_html($row['label']); ?>
							<?php if (!empty($row['note'])) echo '<span class="pi-note">' . esc_html($row['note']) . '</span>'; ?>
							<?php if (!empty($row['badge'])) echo '<span class="pi-badge">' . esc_html($row['badge']) . '</span>'; ?>
                        </td>
                        <td style="text-align: right;">
							<?php echo esc_html($row['value']); ?>
                        </td>
                    </tr>
                </table>
				<?php
			} else {
				?>
                <div class="summary-price-item-w <?php echo esc_attr($extra_class); ?>">
                    <div class="spi-name">
						<?php echo esc_html($row['label']); ?>
						<?php if (!empty($row['note'])) echo '<span class="pi-note">' . esc_html($row['note']) . '</span>'; ?>
						<?php if (!empty($row['badge'])) echo '<span class="pi-badge">' . esc_html($row['badge']) . '</span>'; ?>
                    </div>
                    <div class="spi-price"><?php echo esc_html($row['value']); ?></div>
                </div>
				<?php
			}
		}
		if (!empty($row['sub_items'])) {
			if($inline_styles){
				if (!empty($row['sub_items_heading'])) echo '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 5px; margin-top: 10px;"><tr><td style="color: #788291;font-size: 11px;text-transform: uppercase;letter-spacing: 1px;font-weight: 600;">' . esc_html($row['sub_items_heading']) . '</td><td style="width: 100%;"><div style="height: 1px;background-color: #f1f1f1;margin-left: 10px;"></div></td></tr></table>';
			} else {
				if (!empty($row['sub_items_heading'])) echo '<div class="summary-box-heading"><div class="sbh-item">' . esc_html($row['sub_items_heading']) . '</div><div class="sbh-line"></div></div>';
			}
			foreach ($row['sub_items'] as $row_item) {
				self::output_price_breakdown_row($row_item, $inline_styles);
			}
		}
	}

	public static function is_zero(array $price_breakdown_rows) :bool {
		$subtotal = (float) $price_breakdown_rows['subtotal']['raw_value'];
		return ($subtotal == 0);
	}
}