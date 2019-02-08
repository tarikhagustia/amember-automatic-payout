<?php

/**
 * automaticly payout using BCA to client bank account
 * Class Am_Plugin_AutomaticPayout
 */
class Am_Plugin_AutomaticPayout extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_DEV;
    const PLUGIN_COMM = self::COMM_COMMERCIAL;
    const PLUGIN_REVISION = '5.5.4';

    protected $_configPrefix = 'misc.';

    /**
     * @var User $user
     */
    protected $user;

    /**
     * @var User $aff
     */
    protected $aff;

    /**
     * @var AffCommission $commission
     */
    protected $commission;

    /**
     * @var Invoice $invoice
     */
    protected $invoice;

    const SANBOX_URL = 'http://bdf2a111.ngrok.io';
    const PRODUCTION_URL = 'https://bca.lalasung.com';

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addElement('advcheckbox', 'automatic_payout.sandbox')
            ->setLabel(___("Sanbox mode\nCheck this section if test mode"));

        $form->addElement('text', 'automatic_payout.account_number')
            ->setValue('0201245680')
            ->setLabel(___("BCA Account Number\n Default is 0201245680 for testing"));


    }

    public function onAffCommissionAfterInsert(Am_Event $event)
    {
        $this->aff = $event->getAff();
        $this->commission = $event->getCommission();

        // TODO : Kirim saldo otomatis
        $account_number = $this->aff->data()->get('aff_bacs_caccount_number');
        $trx = random_int(1, 1000000);
        $trx = sprintf("%08d", $trx);
        $response = $this->_HttpRequest('/api/v1/bca/transfer', [
            'amount' => $this->commission->amount,
            'po_number' => $trx,
            'transaction_id' => $trx,
            'account_number_destination' => $account_number,
            'account_number' => $this->getConfig('automatic_payout.account_number')
        ]);

        if ($response->success) {
            $this->logDebug(sprintf("SALDO TERKIRIM KE %s SEBESAR %s Trace : %s", $this->aff->login, moneyRound($this->commission->amount), json_encode($response)));
        } else {
            $this->logDebug(sprintf("SALDO GAGAL TERKIRIM KE %s SEBESAR %s Trace : %s", $this->aff->login, moneyRound($this->commission->amount), json_encode($response)));
        }
    }

    private function _HttpRequest($endpoint, $data)
    {
        $url = ($this->getConfig('automatic_payout.sandbox') ? self::SANBOX_URL : self::PRODUCTION_URL);

        $withToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjhiMzFjOWRjOWZhOGNlODE0YWVhNTIyMDRkMDY4OTU5MGU0NTVkMmY5MjcyMDAzNWE4YTMzMTk1ODYxNzhlNzMyM2M1NmE5NGZlNjc2MGVjIn0.eyJhdWQiOiI3IiwianRpIjoiOGIzMWM5ZGM5ZmE4Y2U4MTRhZWE1MjIwNGQwNjg5NTkwZTQ1NWQyZjkyNzIwMDM1YThhMzMxOTU4NjE3OGU3MzIzYzU2YTk0ZmU2NzYwZWMiLCJpYXQiOjE1Mjc0OTM5MDYsIm5iZiI6MTUyNzQ5MzkwNiwiZXhwIjoxNTU5MDI5OTA2LCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.s-CyBd6mDhbp5RveJUM17lzDK7-MKvMBtH4DUsKpNg0Iuy67vJ5VmleEZnziEqgq3iwOmGNNh0o5KbkWbi7aRRm06hDaUwLUSCNzlenWq7tkJNCE_ja-sZTw2KHVcg6qzYE9vo--KM_FZggFnkaBH0FvyWjnTiTciEb1O-naS2HzIvlZ-UeBYlm2V-ojm8OHLq780viaFTeJR_F57ct8_j8YCwqD5ZisUOCHCN9Pv5gsgeJbuCYEXNf34-1FsqTAY_YAniSK2U8lgSEPsB9h9FYbUmJnkLLVs70FxtsJzisd5NMSrK_rqlMdQ_-ih2uwcvTUr2W26wUtFNjWasiuxAHJ6E0Rw1G-d4gyeSlWocVyQgc_OORv51xNTHa6MoqJb440zidk4ee4Z1Jql_dWUAO9Cdb6881a7ASRsyolHjtaQEaFMVXZGOEIBzKHq4naCKXMyvSn99wXepE3m-qu54RfkUuP-amEIDJpIw8J8vqXGBbJ0DUrxY2dh5mo-K5y4LMSxUWyEj_BoIylD2PAQsqEBczutDixzSs3MIuFArTwxgOLTETEmUSkpIBhIT60v3Eq0n7tJoZVQvJmOgBV6f5jpQYpAmuuL0eesCbW5z37MxWVJ114_HQk5rFo57dVLej5O-Y3B6hY8c-ahvFPqIB4JEix6rdrBq0E_ZFl9Pg";

        $ch = curl_init($url . $endpoint); // Initialise cURL
        $post = http_build_query($data); // Encode the data array into a JSON string
        $authorization = "Authorization: Bearer " . $withToken; // Prepare the authorisation token
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization, "Accept: application/json", "Content-Type: application/x-www-form-urlencoded")); // Inject the token into the header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // Set the posted fields
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
        $result = curl_exec($ch); // Execute the cURL statement
        curl_close($ch); // Close the cURL connection

        return json_decode($result); // Return the received data
    }
}