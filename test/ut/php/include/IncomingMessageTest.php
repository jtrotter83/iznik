<?php

if (!defined('UT_DIR')) {
    define('UT_DIR', dirname(__FILE__) . '/../..');
}
require_once UT_DIR . '/IznikTest.php';
require_once IZNIK_BASE . '/include/message/IncomingMessage.php';

/**
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class IncomingMessageTest extends IznikTest {
    private $dbhr, $dbhm;

    protected function setUp() {
        parent::setUp ();

        global $dbhr, $dbhm;
        $this->dbhr = $dbhr;
        $this->dbhm = $dbhm;
    }

    protected function tearDown() {
        parent::tearDown ();
    }

    public function __construct() {
    }

    public function testBasic() {
        error_log(__METHOD__);

        $msg = file_get_contents('msgs/basic');
        $m = new IncomingMessage($this->dbhr, $this->dbhm);
        $m->parse(IncomingMessage::YAHOO_APPROVED, 'from@test.com', 'to@test.com', $msg);
        assertEquals('Basic test', $m->getSubject());
        assertEquals('Test User', $m->getFromname());
        assertEquals('test@test.com', $m->getFromaddr());
        assertEquals('Hey.', $m->getTextbody());
        assertEquals('from@test.com', $m->getEnvelopefrom());
        assertEquals('to@test.com', $m->getEnvelopeto());
        assertEquals("<HTML><HEAD>
<STYLE id=eMClientCss>
blockquote.cite { margin-left: 5px; margin-right: 0px; padding-left: 10px; padding-right:0px; border-left: 1px solid #cccccc }
blockquote.cite2 {margin-left: 5px; margin-right: 0px; padding-left: 10px; padding-right:0px; border-left: 1px solid #cccccc; margin-top: 3px; padding-top: 0px; }
.plain pre, .plain tt { font-family: monospace; font-size: 100%; font-weight: normal; font-style: normal; white-space: pre-wrap; }
a img { border: 0px; }body {font-family: Tahoma;font-size: 12pt;}
.plain pre, .plain tt {font-family: Tahoma;font-size: 12pt;}</STYLE>
</HEAD>
<BODY>Hey.</BODY></HTML>", $m->getHtmlbody());
        assertEquals(0, count($m->getAttachments()));
        assertEquals(IncomingMessage::TYPE_OTHER, $m->getType());

        # Save it
        $id = $m->save();
        assertNotNull($id);

        # Read it back
        unset($m);
        $m = new IncomingMessage($this->dbhr, $this->dbhm, $id);
        assertEquals('Basic test', $m->getSubject());
        assertEquals('Basic test', $m->getHeader('subject'));
        assertEquals('Test User', $m->getFromname());
        assertEquals('test@test.com', $m->getFromaddr());
        assertEquals('Hey.', $m->getTextbody());
        assertEquals('from@test.com', $m->getEnvelopefrom());
        assertEquals('to@test.com', $m->getEnvelopeto());
        assertEquals('emff7a66f1-e0ed-4792-b493-17a75d806a30@edward-x1', $m->getMessageID());
        assertEquals("<HTML><HEAD>
<STYLE id=eMClientCss>
blockquote.cite { margin-left: 5px; margin-right: 0px; padding-left: 10px; padding-right:0px; border-left: 1px solid #cccccc }
blockquote.cite2 {margin-left: 5px; margin-right: 0px; padding-left: 10px; padding-right:0px; border-left: 1px solid #cccccc; margin-top: 3px; padding-top: 0px; }
.plain pre, .plain tt { font-family: monospace; font-size: 100%; font-weight: normal; font-style: normal; white-space: pre-wrap; }
a img { border: 0px; }body {font-family: Tahoma;font-size: 12pt;}
.plain pre, .plain tt {font-family: Tahoma;font-size: 12pt;}</STYLE>
</HEAD>
<BODY>Hey.</BODY></HTML>", $m->getHtmlbody());
        $m->delete();

        error_log(__METHOD__ . " end");
    }

    public function testAttachment() {
        error_log(__METHOD__);

        $msg = file_get_contents('msgs/attachment');
        $m = new IncomingMessage($this->dbhr, $this->dbhm);
        $m->parse(IncomingMessage::YAHOO_APPROVED, 'from@test.com', 'to@test.com', $msg);
        $atts = $m->getAttachments();
        assertEquals(2, count($atts));
        assertEquals('g4g220x194.png', $atts[0]->getFilename());
        assertEquals('image/png', $atts[0]->getContentType());
        assertEquals('g4g160.png', $atts[1]->getFilename());
        assertEquals('image/png', $atts[1]->getContentType());

        # Save it
        $id = $m->save();
        assertNotNull($id);

        $m->delete();

        error_log(__METHOD__ . " end");
    }

    public function testPending() {
        error_log(__METHOD__);

        $msg = file_get_contents('msgs/approve');
        $m = new IncomingMessage($this->dbhr, $this->dbhm);
        $m->parse(IncomingMessage::YAHOO_PENDING, 'from@test.com', 'to@test.com', $msg);

        assertEquals('Test pending', $m->getSubject());
        assertEquals('Test User', $m->getFromname());
        assertEquals('test@test.com', $m->getFromaddr());
        assertEquals('Test', $m->getTextbody());
        assertEquals('from@test.com', $m->getEnvelopefrom());
        assertEquals('to@test.com', $m->getEnvelopeto());

        $m->delete();

        error_log(__METHOD__ . " end");
    }

    public function testTN() {
        error_log(__METHOD__);

        $msg = file_get_contents('msgs/tn');
        $m = new IncomingMessage($this->dbhr, $this->dbhm);
        $m->parse(IncomingMessage::YAHOO_APPROVED, 'from@test.com', 'to@test.com', $msg);
        assertEquals('20065945', $m->getTnpostid());

        # Save it
        $id = $m->save();
        assertNotNull($id);

        $m->delete();

        error_log(__METHOD__ . " end");
    }

    public function testType() {
        error_log(__METHOD__);

        assertEquals(IncomingMessage::TYPE_OFFER, IncomingMessage::determineType('OFFER: item (location)'));
        assertEquals(IncomingMessage::TYPE_WANTED, IncomingMessage::determineType('[Group]WANTED: item'));

        error_log(__METHOD__ . " end");
    }
}

