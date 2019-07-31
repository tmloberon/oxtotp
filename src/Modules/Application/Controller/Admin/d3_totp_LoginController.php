<?php

/**
 * This Software is the property of Data Development and is protected
 * by copyright law - it is NOT Freeware.
 * Any unauthorized use of this software without a valid license
 * is a violation of the license agreement and will be prosecuted by
 * civil and criminal law.
 * http://www.shopmodule.com
 *
 * @copyright (C) D3 Data Development (Inh. Thomas Dartsch)
 * @author    D3 Data Development - Daniel Seifert <support@shopmodule.com>
 * @link      http://www.oxidmodule.com
 */

namespace D3\Totp\Modules\Application\Controller\Admin;

use D3\Totp\Application\Model\d3totp;
use D3\Totp\Application\Model\d3backupcodelist;
use D3\Totp\Application\Model\Exceptions\d3totp_wrongOtpException;
use Doctrine\DBAL\DBALException;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Registry;

class d3_totp_LoginController extends d3_totp_LoginController_parent
{
    /**
     * @return string
     * @throws DBALException
     * @throws DatabaseConnectionException
     */
    public function render()
    {
        $auth = Registry::getSession()->getVariable("auth");

        $return = parent::render();

        $totp = oxNew(d3totp::class);
        $totp->loadByUserId($auth);

        if ($auth
            && $totp->isActive()
            && false == Registry::getSession()->getVariable(d3totp::TOTP_SESSION_VARNAME)
        ) {
            // set auth as secured parameter;
            Registry::getSession()->setVariable("auth", $auth);
            $this->addTplParam('request_totp', true);
        }

        return $return;
    }

    /**
     * @return mixed|string
     * @throws DBALException
     * @throws DatabaseConnectionException
     */
    public function checklogin()
    {
        $sTotp = Registry::getRequest()->getRequestEscapedParameter('d3totp', true);

        $totp = oxNew(d3totp::class);
        $totp->loadByUserId(Registry::getSession()->getVariable("auth"));

        $return = 'login';

        try {
            if ($this->isNoTotpOrNoLogin($totp)) {
                $return = parent::checklogin();
            } elseif ($this->hasValidTotp($sTotp, $totp)) {
                Registry::getSession()->setVariable(d3totp::TOTP_SESSION_VARNAME, $sTotp);
                $return = "admin_start";
            }
        } catch (d3totp_wrongOtpException $oEx) {
            Registry::getUtilsView()->addErrorToDisplay($oEx);
        }

        return $return;
    }

    /**
     * @return string|void
     * @throws DatabaseConnectionException
     */
    public function getBackupCodeCountMessage()
    {
        $oBackupCodeList = oxNew(d3backupcodelist::class);
        $iCount = $oBackupCodeList->getAvailableCodeCount(Registry::getSession()->getVariable("auth"));

        if ($iCount < 4) {
            return sprintf(
                Registry::getLang()->translateString('D3_TOTP_AVAILBACKUPCODECOUNT'),
                $iCount
            );
        };

        return;
    }

    /**
     * @param d3totp $totp
     * @return bool
     */
    public function isNoTotpOrNoLogin($totp)
    {
        return false == Registry::getSession()->getVariable("auth")
        || false == $totp->isActive();
    }

    /**
     * @param string $sTotp
     * @param d3totp $totp
     * @return bool
     * @throws DatabaseConnectionException
     * @throws d3totp_wrongOtpException
     */
    public function hasValidTotp($sTotp, $totp)
    {
        return Registry::getSession()->getVariable(d3totp::TOTP_SESSION_VARNAME) ||
        (
            $sTotp && $totp->verify($sTotp)
        );
    }

    public function d3CancelLogin()
    {
        $oUser = oxNew(User::class);
        $oUser->logout();
    }
}