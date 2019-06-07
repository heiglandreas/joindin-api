<?php

namespace Joindin\Api\Test\Controller;

use Exception;
use Joindin\Api\Controller\UsersController;
use Joindin\Api\Model\OAuthModel;
use Joindin\Api\Model\UserMapper;
use Joindin\Api\Request;
use Joindin\Api\Service\UserRegistrationEmailService;
use Joindin\Api\View\ApiView;
use Joindin\Api\View\JsonView;
use JoindinTest\Inc\mockPDO;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UsersControllerTest extends TestCase
{

    /**
     * Ensures that if the deleteUser method is called and no user_id is set,
     * an exception is thrown
     *
     * @return void
     */
    public function testDeleteUserWithNoUserIdThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You must be logged in to delete data');
        $this->expectExceptionCode(401);

        $request = new Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/3", 'REQUEST_METHOD' => 'DELETE']);

        $usersController = new UsersController();

        /** @var PDO&MockObject $db */
        $db = $this->getMockBuilder(mockPDO::class)->getMock();

        $usersController->deleteUser($request, $db);
    }

    /**
     * Ensures that if the deleteUser method is called and user_id is a,
     * non-admin, an exception is thrown
     *
     * @return void
     */
    public function testDeleteUserWithNonAdminIdThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You do not have permission to do that');
        $this->expectExceptionCode(403);

        $request = new Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/3", 'REQUEST_METHOD' => 'DELETE']);
        $request->user_id = 2;
        $usersController = new UsersController();


        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(false);

        $usersController->setUserMapper($userMapper);
        $usersController->deleteUser($request, $db);
    }

    /**
     * Ensures that if the deleteUser method is called and user_id is an
     * admin, but the delete fails, then an exception is thrown
     *
     * @return void
     */
    public function testDeleteUserWithAdminAccessThrowsExceptionOnFailedDelete()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('There was a problem trying to delete the user');
        $this->expectExceptionCode(400);

        $request = new Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/3", 'REQUEST_METHOD' => 'DELETE']);
        $request->user_id = 1;
        $usersController = new \Joindin\Api\Controller\UsersController();
        // Please see below for explanation of why we're mocking a "mock" PDO
        // class
        $db = $this->getMockBuilder(mockPDO::class)->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(true);

        $userMapper
            ->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $usersController->setUserMapper($userMapper);
        $usersController->deleteUser($request, $db);
    }


    /**
     * Ensures that if the deleteUser method is called and user_id is an
     * admin, but the delete fails, then an exception is thrown
     *
     * @return void
     */
    public function testDeleteUserWithAdminAccessDeletesSuccessfully()
    {
        $request = new Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/3", 'REQUEST_METHOD' => 'DELETE']);
        $request->user_id = 1;
        $usersController = new UsersController();
        // Please see below for explanation of why we're mocking a "mock" PDO
        // class
        $db = $this->getMockBuilder(mockPDO::class)->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(true);

        $userMapper
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $usersController->setUserMapper($userMapper);
        $this->assertNull($usersController->deleteUser($request, $db));
    }

    public function testThatUserDataIsNotDoubleEscapedOnUserCreation()
    {
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->user_id = 1;
        $request->base = 'base';
        $request->path_info = 'path_info';
        $request->method('getParameter')
            ->withConsecutive(
                ['username'],
                ['full_name'],
                ['email'],
                ['password'],
                ['twitter_username'],
                ['biography']
            )->willReturnOnConsecutiveCalls(
                'user"\'stuff',
                'full"\'stuff',
                'mailstuff@example.com',
                'pass"\'stuff',
                'twitter"\'stuff',
                'Bio"\'stuff'
            );

        $view = $this->getMockBuilder(ApiView::class)->disableOriginalConstructor()->getMock();
        $view->expects($this->once())->method('setHeader')->with('Location', 'basepath_info/1');
        $view->expects($this->once())->method('setResponseCode')->with(201);
        $request->method('getView')->willReturn($view);

        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $userMapper->expects($this->once())->method('getUserByUsername')->with('user"\'stuff')->willReturn(false);
        $userMapper->expects($this->once())->method('checkPasswordValidity')->with('pass"\'stuff')->willReturn(true);
        $userMapper->expects($this->once())->method('generateEmailVerificationTokenForUserId')->willReturn('token');
        $userMapper->expects($this->once())->method('createUser')->with([
            'username' => 'user"\'stuff',
            'full_name' => 'full"\'stuff',
            'email' => 'mailstuff@example.com',
            'password' => 'pass"\'stuff',
            'twitter_username' => 'twitter"\'stuff',
            'biography' => 'Bio"\'stuff'
        ])->willReturn(true);

        $emailService = $this->getMockBuilder(UserRegistrationEmailService::class)->disableOriginalConstructor()->getMock();
        $emailService->method('sendEmail');

        $controller = new UsersController();
        $controller->setUserMapper($userMapper);
        $controller->setUserRegistrationEmailService($emailService);

        $controller->postAction($request, $db);
    }

    public function testThatUserDataIsNotDoubleEscapedOnUserEdit()
    {
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(1);
        $request->method('getParameter')->withConsecutive(
            ['password'],
            ['full_name'],
            ['email'],
            ['username'],
            ['twitter_username'],
            ['biography']
        )->willReturnOnConsecutiveCalls(
            '',
            'full"\'stuff',
            'mailstuff@example.com',
            'user"\'stuff',
            'twitter"\'stuff',
            'Bio"\'stuff'
        );

        $oauthmodel = $this->getMockBuilder(OAuthModel::class)->disableOriginalConstructor()->getMock();
        $oauthmodel->expects($this->once())->method('isAccessTokenPermittedPasswordGrant')->willReturn(true);
        $request->expects($this->once())->method('getOauthModel')->willReturn($oauthmodel);

        $view = $this->getMockBuilder(ApiView::class)->disableOriginalConstructor()->getMock();
        $view->expects($this->once())->method('setHeader')->with('Content-Length', 0);
        $view->expects($this->once())->method('setResponseCode')->with(204);
        $request->method('getView')->willReturn($view);

        $db = $this->getMockBuilder('\PDO')->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)->disableOriginalConstructor()->getMock();
        $userMapper->expects($this->once())->method('getUserByUsername')->with('user"\'stuff')->willReturn(false);
        $userMapper->expects($this->once())->method('thisUserHasAdminOn')->willReturn(true);
        $userMapper->expects($this->once())->method('editUser')->with([
            'username' => 'user"\'stuff',
            'full_name' => 'full"\'stuff',
            'email' => 'mailstuff@example.com',
            'twitter_username' => 'twitter"\'stuff',
            'biography' => 'Bio"\'stuff',
            'user_id' => false,
        ])->willReturn(true);

        $controller = new UsersController();
        $controller->setUserMapper($userMapper);

        $controller->updateUser($request, $db);
    }

    /**
     * Ensures that if the setTrusted method is called and no user_id is set,
     * an exception is thrown
     *
     * @return void
     */
    public function testSetTrustedWithNoUserIdThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You must be logged in to change a user account');
        $this->expectExceptionCode(401);

        $request = new Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/4/trusted", 'REQUEST_METHOD' => 'POST']);
        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $usersController = new UsersController();
        $usersController->setTrusted($request, $db);
    }

    /**
     * Ensures that if the setAdmin method is called and no user_id is set,
     * an exception is thrown
     *
     * @return void
     *
     * @test
     * @expectedException        \Exception
     * @expectedExceptionMessage You must be logged in to change a user account
     * @expectedExceptionCode 401
     */
    public function testSetAdminWithNoUserIdThrowsException()
    {
        $request = new \Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/4/admin", 'REQUEST_METHOD' => 'POST']);

        $usersController = new \UsersController();
        $db = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();

        $usersController->setAdmin($request, $db);
    }

    /**
     * Ensures that if the setTrsuted method is called and user_id is a,
     * non-admin, an exception is thrown
     *
     * @return void
     */
    public function testSetTrustedWithNonAdminIdThrowsException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("You must be an admin to change a user's trusted state");
        $this->expectExceptionCode(403);

        $request = new Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/4/trusted", 'REQUEST_METHOD' => 'POST']);
        $request->user_id = 2;
        $usersController = new UsersController();
        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(false);

        $usersController->setUserMapper($userMapper);
        $usersController->setTrusted($request, $db);
    }

    /**
     * Ensures that if the setAdmin method is called and user_id is a,
     * non-admin, an exception is thrown
     *
     * @return void
     *
     * @test
     * @expectedException        \Exception
     * @expectedExceptionMessage You must be an admin to change a user's admin state
     * @expectedExceptionCode 403
     */
    public function testSetAdminWithNonAdminIdThrowsException()
    {
        $request = new \Request([], ['REQUEST_URI' => "http://api.dev.joind.in/v2.1/users/4/admin", 'REQUEST_METHOD' => 'POST']);
        $request->user_id = 2;
        $usersController = new \UsersController();
        $db = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder('\UserMapper')
            ->setConstructorArgs(array($db,$request))
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->will($this->returnValue(false));

        $usersController->setUserMapper($userMapper);
        $usersController->setAdmin($request, $db);
    }

    /**
     * Ensures that if the setTrusted method is called by an admin,
     * but without a trusted state, an exception is thrown
     *
     * @return void
     */
    public function testSetTrustedWithoutStateThrowsException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You must provide a trusted state');
        $this->expectExceptionCode(400);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(2);
        $request->method('getParameter')
            ->with("trusted")
            ->willReturn(null);

        $usersController = new UsersController();
        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(true);

        $usersController->setUserMapper($userMapper);
        $usersController->setTrusted($request, $db);
    }

    /**
     * Ensures that if the setAdmin method is called by an admin,
     * but without an admin state, an exception is thrown
     *
     * @return void
     *
     * @test
     * @expectedException        \Exception
     * @expectedExceptionMessage You must provide an admin state
     * @expectedExceptionCode 400
     */
    public function testSetAdminWithoutStateThrowsException()
    {
        $request = $this->getMockBuilder('\Request')->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(2);
        $request->method('getParameter')
            ->with("admin")
            ->willReturn(null);

        $usersController = new \UsersController();
        $db = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder('\UserMapper')
            ->setConstructorArgs(array($db,$request))
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(true);

        $usersController->setUserMapper($userMapper);
        $usersController->setAdmin($request, $db);
    }

    /**
     * Ensures that if the setTrusted method is called by an admin,
     * but the update fails, an exception is thrown
     *
     * @return void
     */
    public function testSetTrustedWithFailureThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to update status');
        $this->expectExceptionCode(500);

        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(2);
        $request->method('getParameter')
            ->with("trusted")
            ->willReturn(true);

        $usersController = new UsersController();
        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(true);

        $userMapper
            ->expects($this->once())
            ->method("setTrustedStatus")
            ->willReturn(false);

        $usersController->setUserMapper($userMapper);
        $usersController->setTrusted($request, $db);
    }

    /**
     * Ensures that if the setAdmin method is called by an admin,
     * but the update fails, an exception is thrown
     *
     * @return void
     *
     * @test
     * @expectedException        \Exception
     * @expectedExceptionMessage Unable to update status
     * @expectedExceptionCode 500
     */
    public function testSetAdminWithFailureThrowsException()
    {
        $request = $this->getMockBuilder('\Request')->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(2);
        $request->method('getParameter')
            ->with("admin")
            ->willReturn(true);

        $usersController = new \UsersController();
        $db = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder('\UserMapper')
            ->setConstructorArgs(array($db,$request))
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->will($this->returnValue(true));

        $userMapper
            ->expects($this->once())
            ->method("setAdminStatus")
            ->willReturn(false);

        $usersController->setUserMapper($userMapper);
        $usersController->setAdmin($request, $db);
    }

    /**
     * Ensures that if the setTrusted method is called by an admin,
     * and the update succeeds, a view is created and null is returned
     *
     * @return void
     * @throws \Exception
     */
    public function testSetTrustedWithSuccessCreatesView()
    {
        $request = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(2);
        $request->method('getParameter')
            ->with("trusted")
            ->willReturn(true);

        $view = $this->getMockBuilder(JsonView::class)->getMock();
        $view->expects($this->once())
            ->method("setHeader")
            ->willReturn(true);

        $view->expects($this->once())
            ->method("setResponseCode")
            ->with(204)
            ->willReturn(true);

        $request->expects($this->once())
            ->method("getView")
            ->willReturn($view);

        $usersController = new UsersController();
        $db = $this->getMockBuilder(PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder(UserMapper::class)
            ->setConstructorArgs([$db,$request])
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->willReturn(true);

        $userMapper
            ->expects($this->once())
            ->method("setTrustedStatus")
            ->willReturn(true);

        $usersController->setUserMapper($userMapper);
        $this->assertNull($usersController->setTrusted($request, $db));
    }

    /**
     * Ensures that if the setAdmin method is called by an admin,
     * and the update succeeds, a view is created and null is returned
     *
     * @return void
     * @throws \Exception
     */
    public function testSetAdminWithSuccessCreatesView()
    {
        $request = $this->getMockBuilder('\Request')->disableOriginalConstructor()->getMock();
        $request->method('getUserId')->willReturn(2);
        $request->method('getParameter')
            ->with("admin")
            ->willReturn(true);

        $view = $this->getMockBuilder(\JsonView::class)->getMock();
        $view->expects($this->once())
            ->method("setHeader")
            ->willReturn(true);

        $view->expects($this->once())
            ->method("setResponseCode")
            ->with(204)
            ->willReturn(true);

        $request->expects($this->once())
            ->method("getView")
            ->willReturn($view);

        $usersController = new \UsersController();
        $db = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();

        $userMapper = $this->getMockBuilder('\UserMapper')
            ->setConstructorArgs(array($db,$request))
            ->getMock();

        $userMapper
            ->expects($this->once())
            ->method('isSiteAdmin')
            ->will($this->returnValue(true));

        $userMapper
            ->expects($this->once())
            ->method("setAdminStatus")
            ->willReturn(true);

        $usersController->setUserMapper($userMapper);
        $this->assertNull($usersController->setAdmin($request, $db));
    }
}
