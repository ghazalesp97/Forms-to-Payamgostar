jQuery(document).ready(function($) {
  $('form.form-box').each(function() {
    const form = $(this);

    form.on('submit', function(e) {
      e.preventDefault();

      const btn = form.find('input[type="submit"]');
      const msg = form.find('.payamgostar-message');

      // ✅ Check if reCAPTCHA is present and completed
      if (typeof grecaptcha !== 'undefined') {
        const captchaResponse = grecaptcha.getResponse();
        if (!captchaResponse) {
          msg.html('<span style="color:red;">لطفاً کپچا را تکمیل کنید.</span>');
          return; // stop the submit
        }
      }

      btn.prop('disabled', true).val('در حال ارسال...');
      msg.html('');

      // add WordPress AJAX action
      const formData = form.serialize() + '&action=payamgostar_submit';

      $.post(Payamgostar.ajax_url, formData, function(response) {
        if (response.success) {
          msg.html('<span class="success-message" style="color:green;">' + response.data.message + '</span>');
          form[0].reset();

          // ✅ Reset the CAPTCHA for next submission
          if (typeof grecaptcha !== 'undefined') {
            grecaptcha.reset();
          }
        } else {
          msg.html('<span style="color:red;">' + response.data.message + '</span>');
        }
      }).fail(function() {
        msg.html('<span style="color:red;">خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.</span>');
      }).always(function() {
        setTimeout(() => {
          btn.prop('disabled', false).val('ثبت درخواست مشاوره');
        }, 3000);
      });
    });
  });
});
