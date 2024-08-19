Disallow random code injection in Magento 2 trough API or WEB requests for:
**Order Creation, Customer Creation, Customer Name Update, Customer Address Update**

Email notifications for each unsuccessful attempt.
Set your email in these 4 files:
    **AddressSavePlugin.php, CreateAccountPlugin.php, CustomerSavePlugin.php, OrderSourceLogger.php**
    
If you don't like to receive notifications -> comment:

        $command = 'echo "' . addslashes($message) . '" | mailx -s "Unsuccessful attempt" your@email.com';
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \Exception("Failed to send email. Command output: " . implode("\n", $output));
        }
All requests will be saved here:     **'/magento/var/log/custom_order.log'**;

      Unsuccessful order attempt:
      Error Message: Invalid characters in First Name.
      IP: X.X.X.X, 127.0.0.1
      User Agent: Mozilla/5.0 (Linux; Android 9; SM-G950U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36
      Request URI: /rest/default/V1/guest-carts/6DxbMhXoXtUDcOrpOU2EMqOmGzITsEIy/payment-information

      Unsuccessful attempt:
      Error Message: Invalid characters in Postcode.
      IP: X.X.X.X, 127.0.0.1
      User Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36
      Request URI: /customer/account/editPost/
