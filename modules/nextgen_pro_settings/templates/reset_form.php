<table>
    <tr>
        <td class='column1'>
            <span class='tooltip' title="<?php echo $reset_non_ecommerce_tooltip; ?>">
                <?php echo $reset_non_ecommerce_label; ?>
            </span>
        </td>
        <td>
            <input type="submit"
                   class="button-primary"
                   data-confirm="<?php echo esc_attr($reset_non_ecommerce_confirmation); ?>"
                   data-proxy-value="reset_non_ecommerce_settings"
                   name="action_proxy"
                   value="<?php echo esc_attr($reset_non_ecommerce_value); ?>"/>
            <p><em><?php echo esc_html($reset_non_ecommerce_warning); ?></em></p>
        </td>
    </tr>
    <tr>
        <td class='column1'>
            <span class='tooltip' title="<?php echo $reset_ecommerce_tooltip; ?>">
                <?php echo $reset_ecommerce_label; ?>
            </span>
        </td>
        <td>
            <input type="submit"
                   class="button-primary"
                   data-confirm="<?php echo esc_attr($reset_ecommerce_confirmation); ?>"
                   data-proxy-value="reset_ecommerce_settings"
                   name="action_proxy"
                   value="<?php echo esc_attr($reset_ecommerce_value); ?>"/>
            <p><em><?php echo esc_html($reset_ecommerce_warning); ?></em></p>
        </td>
    </tr>
</table>