<?php

require_once 'controllers/AutoPayoutController.php';

class AutoPayout extends Am_Record
{
    protected $_key = 'id';
    protected $_table = '?_autopayout';
    protected $_user = null;

    /**
     * @return User $user
     * @throws Am_Exception_InternalError
     */
    public function getFromUser()
    {
        if (empty($this->user_id))
            return $this->_user = null;
        if (empty($this->_user) || $this->_user->user_id != $this->user_id) {
            $this->_user = $this->getDi()->userTable->load($this->user_id);
        }
        return $this->_user;
    }

}

class AutoPayoutTable extends Am_Table
{
    protected $_key = 'id';
    protected $_table = '?_autopayout';
    protected $_recordClass = 'AutoPayout';
}

/**
 * automaticly payout using BCA to client bank account
 * Class Am_Plugin_AutomaticPayout
 */
class Am_Plugin_AutomaticPayout extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_COMM = self::COMM_COMMERCIAL;
    const PLUGIN_REVISION = '5.5.4';

    protected $_configPrefix = 'misc.';

    protected $_table;

    /**
     * After payout transfer to BCA this event will called
     * @param User $user
     * @param User $aff
     * @param Invoice $invoice
     * @param double $amount
     * @param string $reff Reference from BCA
     *
     */
    const AFTER_PAYOUT_TRANSFER = 'afterPayoutTransfer';
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

    const SANBOX_URL = 'http://bca.lalasung.local';
    const PRODUCTION_URL = 'https://bca.lalasung.com';
    const TOKEN_PROD = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjhiMzFjOWRjOWZhOGNlODE0YWVhNTIyMDRkMDY4OTU5MGU0NTVkMmY5MjcyMDAzNWE4YTMzMTk1ODYxNzhlNzMyM2M1NmE5NGZlNjc2MGVjIn0.eyJhdWQiOiI3IiwianRpIjoiOGIzMWM5ZGM5ZmE4Y2U4MTRhZWE1MjIwNGQwNjg5NTkwZTQ1NWQyZjkyNzIwMDM1YThhMzMxOTU4NjE3OGU3MzIzYzU2YTk0ZmU2NzYwZWMiLCJpYXQiOjE1Mjc0OTM5MDYsIm5iZiI6MTUyNzQ5MzkwNiwiZXhwIjoxNTU5MDI5OTA2LCJzdWIiOiIxIiwic2NvcGVzIjpbXX0.s-CyBd6mDhbp5RveJUM17lzDK7-MKvMBtH4DUsKpNg0Iuy67vJ5VmleEZnziEqgq3iwOmGNNh0o5KbkWbi7aRRm06hDaUwLUSCNzlenWq7tkJNCE_ja-sZTw2KHVcg6qzYE9vo--KM_FZggFnkaBH0FvyWjnTiTciEb1O-naS2HzIvlZ-UeBYlm2V-ojm8OHLq780viaFTeJR_F57ct8_j8YCwqD5ZisUOCHCN9Pv5gsgeJbuCYEXNf34-1FsqTAY_YAniSK2U8lgSEPsB9h9FYbUmJnkLLVs70FxtsJzisd5NMSrK_rqlMdQ_-ih2uwcvTUr2W26wUtFNjWasiuxAHJ6E0Rw1G-d4gyeSlWocVyQgc_OORv51xNTHa6MoqJb440zidk4ee4Z1Jql_dWUAO9Cdb6881a7ASRsyolHjtaQEaFMVXZGOEIBzKHq4naCKXMyvSn99wXepE3m-qu54RfkUuP-amEIDJpIw8J8vqXGBbJ0DUrxY2dh5mo-K5y4LMSxUWyEj_BoIylD2PAQsqEBczutDixzSs3MIuFArTwxgOLTETEmUSkpIBhIT60v3Eq0n7tJoZVQvJmOgBV6f5jpQYpAmuuL0eesCbW5z37MxWVJ114_HQk5rFo57dVLej5O-Y3B6hY8c-ahvFPqIB4JEix6rdrBq0E_ZFl9Pg';
    const TOKEN_DEV = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjYwZTA5ZjQzZGI0NTkzMzY0YjVkNGJhN2UzNjE1ZDkzZjRlYzM0YTY5NDliOWNkZjQyNTg2NzU0NDVlNjEwNDFiYzhjZWI3ZTMxZjM3MGNhIn0.eyJhdWQiOiIxMCIsImp0aSI6IjYwZTA5ZjQzZGI0NTkzMzY0YjVkNGJhN2UzNjE1ZDkzZjRlYzM0YTY5NDliOWNkZjQyNTg2NzU0NDVlNjEwNDFiYzhjZWI3ZTMxZjM3MGNhIiwiaWF0IjoxNTUwNDY4OTM3LCJuYmYiOjE1NTA0Njg5MzcsImV4cCI6MTU4MjAwNDkzNywic3ViIjoiMSIsInNjb3BlcyI6WyIqIl19.UKz3Y2RuwmaBn4yA-Jpo-j-8Arfx6Ok4tmSdCB1azi2E5sJMIpOhe791yWH7fl1K0QWo4r1djbnGxwlnQK_DGzTnfsLIzOixcFUmG393NFEjAmGzvcs538dxsZNIgVKNhJ7rBaH4isSUx_uk5YUQAbWdlcdLVEPSwnklP-rTJwJtgX6e50t8Oo4GyPI9O0PVKQJeMOZId7XavEJkE5d_w15R9LsKYB3YZYVNYA4MGOINKWvGfYSKPMDANpbMsUz1ZCgrfxF5YoKd2m2XALlWgWJIv5SOWM7ncqt873V4FneeJ0VRUoJhyD26hAxfHz-Ia8YE8mUV3ad6m-5dFHYQxVm5VET3nMYM3phDJWQ0GOp_KV_AqK94ZISxHqKt6EDVPHIyYdK6lpJe1g14ji7rotuLNSlDoSYcsfl7ymk_0oagT8r0g1VA7r79LGms_gxeZsMG8BvGYovNfeL0s8dfAvnyCCfXMXqGFEzj5FX95yehBcjgBS_4iSBWWTUigI0HrAsSEhPlPjjuRl4BCPLW_nQfk1B7xLpamtMxvvanKMAExGkXYI0j5aX1nX0w73rgDGYZKbU7ycizbrjCEWVRhxLv-YezrN2ZrMei-LwOoo6k6N3pkp89L3jOq-3lVmHocKtIesPm9sztEEbMtgaQLtl9Yotnh9tXW5WlL0vzYiI';

    public function onUserMenuItems(Am_Event $e)
    {
        $e->addReturn(array($this, 'buildMenu'), 'autopayout');
    }

    public function buildMenu(Am_Navigation_Container $nav, User $user, $order, $config)
    {
        return $nav->addPage(array(
            'id' => 'autopayout',
            'controller' => 'autopayout',
            'action' => 'index',
            'label' => ___('Mutasi'),
            'order' => $order
        ));
    }

    public function getTitle()
    {
        return "Automatic Payout BCA";
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addElement('advcheckbox', 'automatic_payout.sandbox')
            ->setLabel(___("Sanbox mode\nCheck this section if test mode"));

        $form->addElement('text', 'automatic_payout.account_number')
            ->setValue('0201245680')
            ->setLabel(___("BCA Account Number\n Default is 0201245680 for testing"));

        $set = $form->addFieldset('', array('id' => 'conditions'))
            ->setLabel(___('Misc'));

        $set->addElement('text', 'automatic_payout.admin_account_number')
            ->setLabel(___("BCA Admin Account Number\nWill be trasnfered"));

        $set->addElement(new Am_Form_Element_AutoPayoutAdminCommission(null, null, 'automatic_payout.admin_commission_amount'))
            ->setLabel(___("Admin Commission\nvalue of commission received by admin"));

    }

    /**
     * @param Am_Event $event
     * @throws Exception
     */
    public function onAffCommissionAfterInsert(Am_Event $event)
    {
        $this->aff = $event->getAff();
        $this->commission = $event->getCommission();
        $this->invoice = $event->getInvoice();

        // TODO : Kirim saldo otomatis
        $account_number = $this->aff->data()->get('aff_bacs_caccount_number');
        $trx = random_int(1, 1000000);
        $trx = sprintf("%08d", $trx);
        $response = $this->_HttpRequest('/api/v1/bca/transfer', [
            'amount' => $this->commission->amount,
            'po_number' => $trx,
            'transaction_id' => $trx,
            'account_number_destination' => $account_number,
            'account_number' => $this->getConfig('automatic_payout.account_number'),
            'remark' => sprintf("eBuset - %s", $this->invoice->public_id)
        ]);

        if ($response->success) {
            $this->logDebug(sprintf("SALDO TERKIRIM KE %s DARI DOWNLINE %s LEVEL %s SEBESAR %s, Trace : %s", $this->aff->login, $event->getUser()->login, $this->commission->tier, moneyRound($this->commission->amount), json_encode($response)));
        } else {
            $this->logDebug(sprintf("SALDO GAGAL TERKIRIM KE %s DARI DOWNLINE %s LEVEL %s SEBESAR %s, Trace : %s", $this->aff->login, $event->getUser()->login, $this->commission->tier, moneyRound($this->commission->amount), json_encode($response)));
        }

        $this->getDi()->hook->call(self::AFTER_PAYOUT_TRANSFER, [
            'aff' => $this->aff,
            'user' => $event->getUser(),
            'invoice' => $this->invoice,
            'amount' => $this->commission->amount,
            'reff' => $trx,
            'message' => $response,
            'remark' => sprintf("eBuset - %s", $this->invoice->public_id),
            'commission' => $this->commission,
            'status' => ($response->success)
        ]);
    }

    private function _HttpRequest($endpoint, $data)
    {
        $url = ($this->getConfig('automatic_payout.sandbox') ? self::SANBOX_URL : self::PRODUCTION_URL);

        $withToken = ($this->getConfig('automatic_payout.sandbox') ? self::TOKEN_DEV : self::TOKEN_PROD);
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

    public function onInvoiceStarted(Am_Event $event)
    {
        $invoice = $event->getInvoice();
        $destination = $this->getConfig('automatic_payout.admin_account_number');
        if (!$destination)
            return false;

        $trx = random_int(1, 1000000);
        $trx = sprintf("%08d", $trx);

        if ($this->getConfig('automatic_payout.admin_commission_amount_t') == '$') {
            $amount = $this->getConfig('automatic_payout.admin_commission_amount_t');
        } else {
            $amount = $invoice->first_total * $this->getConfig('automatic_payout.admin_commission_amount_t') / 100;
            $amount = ($amount <= 10000) ? 10000 : $amount;
        }

        $response = $this->_HttpRequest('/api/v1/bca/transfer', [
            'amount' => $amount,
            'po_number' => $trx,
            'transaction_id' => $trx,
            'account_number_destination' => $destination,
            'account_number' => $this->getConfig('automatic_payout.account_number'),
            'remark' => sprintf("eBuset - %s", $invoice->public_id)
        ]);

        if ($response->success) {
            $this->logDebug(sprintf("SALDO TERKIRIM KE ADMIN DARI INVOICE %s SEBESAR %s, TRACE : %s ", $invoice->public_id, $amount, json_encode($response)));
        } else {
            $this->logDebug(sprintf("SALDO GAGAL TERKIRIM KE ADMIN DARI INVOICE %s SEBESAR %s, TRACE : %s ", $invoice->public_id, $amount, json_encode($response)));
        }
    }

    public static function getDbXml()
    {
        return <<<CUT
<schema version="4.0.0">
    <table name="autopayout">
        <field name="id" type="int" notnull="1" extra="auto_increment"/>
        <field name="aff_id" type="int" unsigned="1" notnull="0"/>
        <field name="user_id" type="int" unsigned="1" notnull="0"/>
        <field name="tier" type="int" notnull="0"/>
        <field name="account_name" type="varchar" len="50"></field>
        <field name="account_number" type="varchar" len="50"></field>
        <field name="account_bank_code" type="varchar" len="50"></field>
        <field name="amount" type="double"></field>
        <field name="reff_id" type="varchar" len="50"></field>
        <field name="created_at" type="datetime"></field>
        <field name="status" type="tinyint"></field>
        <field name="comment" type="varchar" len="100"></field>
        <field name="payload" type="text"></field>
        <index name="aff_id">
          <field name="aff_id"/>
          <field name="user_id"/>
        </index>
        <index name="PRIMARY" unique="1">
          <field name="id"/>
        </index>
    </table>
</schema>
CUT;
    }

    public function onAfterPayoutTransfer(Am_Event $event)
    {
        $aff = $event->getAff();
        $user = $event->getUser();
        $commission = $event->getCommission();
        $message = $event->getMessage();

        $record = $this->getTable()->createRecord();
        $record->aff_id = $aff->user_id;
        $record->user_id = $user->user_id;
        $record->tier = $commission->tier;
        $record->account_name = $aff->data()->get('aff_bacs_account_holder_name');
        $record->account_number = $aff->data()->get('aff_bacs_caccount_number');
        $record->account_bank_code = $aff->data()->get('aff_bacs_bank_name');
        $record->amount = $commission->amount;
        $record->reff_id = $event->getReff();
        $record->created_at = sqlTime(time());
        $record->status = $event->getStatus();
        $record->comment = (!$message->success) ? $message->results->ErrorMessage->Indonesian : 'SUKSES';
        $record->payload = json_encode($event->getMessage());
        $record->save();

    }

    /**
     * @return AutoPayoutTable
     */
    public function getTable()
    {
        if (!$this->_table) {
            $this->_table = $this->getDi()->autoPayoutTable;
        }
        return $this->_table;
    }
}


class Am_Form_Element_AutoPayoutAdminCommission extends HTML_QuickForm2_Container_Group
{
    public function __construct($name = null, $attributes = null, $data = null)
    {
        parent::__construct($name, $attributes, null);
        $this->setSeparator(' ');
        $this->addText($data . '_c', array('size' => 5));
        $this->addSelect($data . '_t')
            ->loadOptions(array(
                '%' => '%',
                '$' => Am_Currency::getDefault()
            ));
    }
}