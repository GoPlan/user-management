<?php
/**
 * Created by PhpStorm.
 *
 * Duc-Anh LE (ducanh.ke@gmail.com)
 *
 * User: ducanh-ki
 * Date: 3/4/17
 * Time: 8:47 AM
 */

namespace CreativeDelta\User\Core\Impl\Service;


use CreativeDelta\User\Core\Domain\Entity\Identity;
use CreativeDelta\User\Core\Domain\Entity\SessionLog;
use CreativeDelta\User\Core\Domain\UserIdentityServiceInterface;
use CreativeDelta\User\Core\Domain\UserRegisterMethodAdapter;
use CreativeDelta\User\Core\Domain\UserSessionServiceInterface;
use CreativeDelta\User\Core\Impl\Exception\UserIdentityException;
use CreativeDelta\User\Core\Impl\Row\IdentityRow;
use CreativeDelta\User\Core\Impl\Table\UserIdentityTable;
use CreativeDelta\User\Core\Impl\Table\UserSessionLogTable;
use Zend\Crypt\Password\Bcrypt;
use Zend\Db\Adapter\Adapter;
use Zend\Hydrator\ClassMethods;

class UserIdentityService implements UserIdentityServiceInterface, UserSessionServiceInterface
{
    const AUTHENTICATION_SERVICE_NAME = 'Zend\Authentication\AuthenticationService';

    protected $bcrypt;
    protected $dbAdapter;
    protected $userIdentityTable;
    protected $userSignInLogTable;
    protected $userSessionService;

    function __construct(Adapter $dbAdapter)
    {
        $this->dbAdapter          = $dbAdapter;
        $this->bcrypt             = new Bcrypt();
        $this->userIdentityTable  = new UserIdentityTable($dbAdapter);
        $this->userSignInLogTable = new UserSessionLogTable($dbAdapter);
        $this->userSessionService = new UserSessionService($this->dbAdapter);
    }

    /**
     * @param $previousHash
     * @param $returnUrl
     * @param $data
     * @return string
     */
    public function createSessionLog($previousHash = null, $returnUrl = null, $data = null)
    {
        return $this->userSessionService->createSessionLog($previousHash, $returnUrl, $data);
    }

    /**
     * @param $hash
     * @return SessionLog|null
     */
    public function getSessionLog($hash)
    {
        return $this->userSessionService->getSessionLog($hash);
    }

    /**
     * @param Identity|string $identity
     * @return bool
     */
    public function hasIdentity($identity)
    {
        return $this->userIdentityTable->hasIdentity($identity);
    }

    /**
     * @param string $identity
     * @return null|Identity
     */
    public function getIdentityByIdentity($identity)
    {
        $result = $this->userIdentityTable->getByIdentity($identity);

        /** @var Identity $identity */
        $identity = $result ? (new ClassMethods())->hydrate($result->getArrayCopy(), new Identity()) : null;

        return $identity;
    }

    /**
     * @param $identityId
     * @return Identity|null
     */
    public function getIdentityById($identityId)
    {
        $result = $this->userIdentityTable->get($identityId);

        /** @var Identity $identity */
        $identity = $result ? (new ClassMethods())->hydrate($result->getArrayCopy(), new Identity()) : null;

        return $identity;
    }

    /**
     * @param UserRegisterMethodAdapter $adapter
     * @param string                    $account
     * @param int                       $userId
     * @param null                      $data
     * @return mixed
     * @throws UserIdentityException
     */
    public function register(UserRegisterMethodAdapter $adapter, $account, $userId, $data = null)
    {
        if ($this->hasIdentity($account) || $adapter->has($userId))
            throw new UserIdentityException(UserIdentityException::CODE_ERROR_INSERT_ACCOUNT_ALREADY_EXIST);

        $dbConnection = $this->dbAdapter->getDriver()->getConnection();
        $dbConnection->beginTransaction();

        try {

            $identity = new IdentityRow($this->userIdentityTable);
            $identity->setAutoSequence(UserIdentityTable::AUTO_SEQUENCE);
            $identity->setIdentity($account);
            $identity->setState(Identity::STATE_ACTIVE);
            $identity->save();

            $adapter->register($identity->getId(), $userId, $data);
            $dbConnection->commit();

            return $identity->getId();

        } catch (\Exception $exception) {

            $dbConnection->rollback();
            throw new UserIdentityException(UserIdentityException::CODE_ERROR_INSERT_DATABASE_OPERATION_FAILED, $exception);
        }
    }
}