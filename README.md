Disallow random code injection in Magento 2 trough API or WEB requests for:
**Order Creation, Customer Creation, Customer Name Update, Customer Address Update**

Email notifications for each unsuccessful attempt.
Set your email in these 4 files:
    **AddressSavePlugin.php, CreateAccountPlugin.php, CustomerSavePlugin.php, OrderSourceLogger.php**
    
If you don't like to receive notification comment:

        $command = 'echo "' . addslashes($message) . '" | mailx -s "Unsuccessful attempt" your@email.com';
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new \Exception("Failed to send email. Command output: " . implode("\n", $output));
        }
All request will be saved here:     **'/var/log/custom_order.log'**;
