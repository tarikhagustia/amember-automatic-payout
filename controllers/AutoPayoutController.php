<?php

class AutoPayoutController extends Am_Mvc_Controller_Grid
{
    protected $layout = 'layout.phtml';

    function createGrid()
    {
        $ds = new Am_Query($this->getDi()->autoPayoutTable);
        $ds = $ds->addWhere('aff_id=?', $this->user_id)->addOrder('created_at', true);
        $grid = new Am_Grid_ReadOnly('_autopayouts', ___('Payout Mutation'), $ds, $this->getRequest(), $this->getView(), $this->getDi());
        $grid->setCountPerPage(
            Am_Di::getInstance()->config->get('credits.count_row_page', Am_Di::getInstance()->config->get('admin.records-on-page', 10))
        );
        $grid->addField(new Am_Grid_Field_Date('created_at', ___('Date')));
        $grid->addField('reff_id', ___('Reff'));
        $grid->addField('from_user', ___('From User'))->setRenderFunction(
            function ($record) {
                return sprintf("<td>%s (%s)</td>", $record->getFromUser()->login,  $record->getFromUser()->getName());
            }
        );
        $grid->addField('amount', ___('Amount'))->setRenderFunction(
            function ($record) {
                return "<td>" .  Am_Currency::render($record->amount) . "</td>";
            }
        );


        $grid->addField('comment', ___('Comment'));
        return $grid;
    }


    function preDispatch()
    {
        $this->getDi()->auth->requireLogin($this->getUrl());
        $this->user_id = $this->getDi()->auth->getUserId();
        if ($this->getDi()->config->get('credits.hide_credit_history_tab')
            && $this->getDi()->config->get('credits.hide_credit_balance_link'))
            throw new Am_Exception_AccessDenied("You have no enough permissions for this operation");

        parent::preDispatch();
    }
}