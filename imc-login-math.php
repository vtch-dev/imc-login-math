<?php
/**
 * Plugin Name: IMC Login Math Check
 * Description: Simple Arithmetic Bot Verification.
 * Plugin URI: https://imc.ge/wordpress/
 * Author: IMC
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

class IMC_Login_Math_Check {
    const TRANSIENT_PREFIX = 'imc_math_';
    const TTL = 15 * 60; // 15 წუთი

    public function __construct() {
        add_action('login_form', [$this,'render_field']);                 // wp-login.php ფორმაზე
        add_filter('authenticate', [$this,'validate'], 30, 3);            // ლოგინის ვალიდაცია
        add_action('woocommerce_login_form', [$this,'render_field']);     // თუ WooCommerce გაქვს
    }

    /** ვაჩვენებთ ამოცანას + ვქმნით transient-ს პასუხით */
    public function render_field() {
        // ავაგოთ ამოცანა (რეზულტატი 0..10 დიაპაზონში)
        $ops = ['+','-'];
        do {
            $a = rand(0,10);
            $b = rand(0,10);
            $op = $ops[array_rand($ops)];
            $ans = ($op === '+') ? ($a + $b) : ($a - $b);
        } while ($ans < 0 || $ans > 10); // „10-ის ფარგლებში“

        // ვქმნით ტოკენს და ვინახავთ სწორ პასუხს transient-ში
        $token = wp_generate_password(16, false, false);
        set_transient(self::TRANSIENT_PREFIX.$token, (string)$ans, self::TTL);

        ?>
        <p>
            <label for="imc_math_answer"><?php echo esc_html__('დამადასტურებელი კითხვა', 'imc-login-math'); ?></label>
            <br/>
            <span style="display:inline-block;margin:6px 0;padding:6px 10px;background:#f6f7f7;border:1px solid #ccd0d4;border-radius:6px;">
                <?php echo esc_html($a.' '.$op.' '.$b.' = ?'); ?>
            </span>
            <input type="number" min="0" max="10" step="1" name="imc_math_answer" id="imc_math_answer" class="input" value="" size="2" style="width:90px;" required />
            <input type="hidden" name="imc_math_token" value="<?php echo esc_attr($token); ?>">
        </p>
        <?php
    }

    /** ვალიდაცია: პასუხის შემოწმება ავტორიზაციამდე */
    public function validate($user, $username, $password) {
        // თუ ადმინშია programmatic auth ან REST → გამოტოვება
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) return $user;

        // POST ველები საჭიროა
        $token  = isset($_POST['imc_math_token']) ? sanitize_text_field(wp_unslash($_POST['imc_math_token'])) : '';
        $answer = isset($_POST['imc_math_answer']) ? trim(wp_unslash($_POST['imc_math_answer'])) : '';

        // თუ ვერ მივიღეთ ველები → შეცდომა
        if ($token === '' || $answer === '') {
            return new WP_Error('imc_math_missing', __('გთხოვ, პასუხი მიუთითე ვერიფიკაციის ველში.', 'imc-login-math'));
        }

        // წაიკითხე სწორი პასუხი transient-იდან
        $key = self::TRANSIENT_PREFIX.$token;
        $expected = get_transient($key);

        // თუ ვადა გასულია/არ არსებობს
        if ($expected === false) {
            return new WP_Error('imc_math_expired', __('ვერიფიკაციის დრო ამოიწურა — ცადე თავიდან.', 'imc-login-math'));
        }

        // წაშალე ერთჯერადად
        delete_transient($key);

        // შეადარე (ციფრული შედარება)
        if ((string)intval($answer) !== (string)intval($expected)) {
            return new WP_Error('imc_math_wrong', __('არასწორი პასუხია ვერიფიკაციაზე.', 'imc-login-math'));
        }

        // წარმატება → ვაბრუნებთ user-ს უცვლელად
        return $user;
    }
}

new IMC_Login_Math_Check();

