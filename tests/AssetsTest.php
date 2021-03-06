<?php

namespace flusio;

class AssetsTest extends \PHPUnit\Framework\TestCase
{
    use \Minz\Tests\InitializerHelper;
    use \Minz\Tests\ApplicationHelper;
    use \Minz\Tests\ResponseAsserts;

    public function testShowReturnsTheAsset()
    {
        $response = $this->appRun('get', '/src/assets/javascripts/application.js');

        $this->assertResponse($response, 200);
        $this->assertHeaders($response, [
            'Content-Type' => 'text/javascript',
        ]);
    }

    public function testShowReturns404IfFileDoesntExist()
    {
        $response = $this->appRun('get', '/src/assets/dont_exist.js');

        $this->assertResponse($response, 404, 'This file doesn’t exist.');
    }

    public function testShowReturns404IfFileCannotBeAccessed()
    {
        $response = $this->appRun('get', '/src/assets/../Application.php');

        $this->assertResponse($response, 404, 'You’ll not get this file!');
    }
}
