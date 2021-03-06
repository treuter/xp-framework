<?php
/* This class is part of the XP framework
 *
 * $Id$
 */

  uses(
    'unittest.TestCase',
    'io.streams.MemoryInputStream',
    'io.streams.MemoryOutputStream',
    'io.streams.Streams',
    'lang.Process',
    'lang.Runtime',
    'peer.ftp.FtpConnection'
  );

  /**
   * TestCase for FTP API.
   *
   * @see      xp://peer.ftp.FtpConnection
   * @purpose  Unittest
   */
  class IntegrationTest extends TestCase {
    protected static
      $serverProcess = NULL,
      $bindAddress   = NULL;

    protected
      $conn          = NULL;

    /**
     * Sets up test case
     *
     */
    #[@beforeClass]
    public static function startFtpServer() {

      // Arguments to server process
      $args= array(
        'debugServerProtocolToFile' => NULL,   
      );

      // Start server process
      self::$serverProcess= Runtime::getInstance()->newInstance(
        NULL, 
        'class', 
        'net.xp_framework.unittest.peer.ftp.TestingServer',
        array_values($args)
      );
      self::$serverProcess->in->close();

      // Check if startup succeeded
      $status= self::$serverProcess->out->readLine();
      if (1 != sscanf($status, '+ Service %[0-9.:]', self::$bindAddress)) {
        try {
          self::shutdownFtpServer();
        } catch (IllegalStateException $e) {
          $status.= $e->getMessage();
        }
        throw new PrerequisitesNotMetError('Cannot start FTP server: '.$status, NULL);
      }
    }

    /**
     * Shut down FTP server
     *
     */
    #[@afterClass]
    public static function shutdownFtpServer() {

      // Tell the FTP server to shut down
      try {
        $c= new FtpConnection('ftp://test:test@'.self::$bindAddress);
        $c->connect();
        $c->sendCommand('SHUTDOWN');
        $c->close();
      } catch (Throwable $ignored) {
        // Fall through, below should terminate the process anyway
      }

      $status= self::$serverProcess->out->readLine();
      if (!strlen($status) || '+' != $status{0}) {
        while ($l= self::$serverProcess->out->readLine()) {
          $status.= $l;
        }
        while ($l= self::$serverProcess->err->readLine()) {
          $status.= $l;
        }
        self::$serverProcess->close();
        throw new IllegalStateException($status);
      }
      self::$serverProcess->close();
    }

    /**
     * Sets up test case
     *
     */
    public function setUp() {
      $this->conn= new FtpConnection('ftp://test:test@'.self::$bindAddress.'?passive=1&timeout=1');
    }

    /**
     * Sets up test case
     *
     */
    public function tearDown() {
      $this->conn->close();
    }

    /**
     * Test connecting and logging in
     *
     */
    #[@test]
    public function connect() {
      $this->conn->connect();
    }

    /**
     * Test connecting and logging in with incorrect credentials
     *
     */
    #[@test, @expect('peer.AuthenticationException')]
    public function incorrectCredentials() {
      create(new FtpConnection('ftp://test:INCORRECT@'.self::$bindAddress.'?timeout=1'))->connect();
    }

    /**
     * Test retrieving root directory
     *
     */
    #[@test]
    public function rootDir() {
      $this->conn->connect();
      with ($root= $this->conn->rootDir()); {
        $this->assertClass($root, 'peer.ftp.FtpDir');
        $this->assertEquals('/', $root->getName());
      }
    }

    /**
     * Test retrieving root directory's contents
     *
     */
    #[@test]
    public function entries() {
      $this->conn->connect();
      $entries= $this->conn->rootDir()->entries();
      $this->assertClass($entries, 'peer.ftp.FtpEntryList');
      $this->assertFalse($entries->isEmpty());
      foreach ($entries as $entry) {
        $this->assertSubClass($entry, 'peer.ftp.FtpEntry');
      }
    }

    /**
     * Test
     *
     */
    #[@test]
    public function sendCwd() {
      $this->conn->connect();
      $r= $this->conn->sendCommand('CWD %s', '/htdocs/');
      $this->assertEquals('250 "/htdocs" is new working directory', $r[0]);
    }

    /**
     * Test
     *
     */
    #[@test]
    public function listingWithoutParams() {
      $this->conn->connect();
      $this->conn->sendCommand('CWD %s', '/htdocs/');
      $r= $this->conn->listingOf(NULL);
      $list= implode("\n", $r);
      $this->assertEquals(TRUE, (bool)strpos($list, 'index.html'), $list);
    }

    /**
     * Test
     *
     */
    #[@test]
    public function cwdBackToRoot() {
      $this->sendCwd();
      $r= $this->conn->sendCommand('CWD %s', '/');
      $this->assertEquals('250 "/" is new working directory', $r[0]);
    }

    /**
     * Test
     *
     */
    #[@test]
    public function cwdRelative() {
      $this->conn->connect();
      $r= $this->conn->sendCommand('CWD %s', '/outer/inner');
      $this->assertEquals('250 "/outer/inner" is new working directory', $r[0]);

      $r= $this->conn->sendCommand('CDUP');
      $this->assertEquals('250 CDUP command successful', $r[0]);

      $r= $this->conn->sendCommand('CWD inner');
      $this->assertEquals('250 "/outer/inner" is new working directory', $r[0]);
    }

    /**
     * Test retrieving the ".trash" directory which is empty.(except for
     * the ".svn" directory, if test runs within svn checkout).
     *
     */
    #[@test]
    public function dotTrashDir() {
      $this->conn->connect();
      with ($r= $this->conn->rootDir()); {
        $this->assertTrue($r->hasDir('.trash'));
        $dir= $r->getDir('.trash');
        $this->assertClass($dir, 'peer.ftp.FtpDir');
        $this->assertEquals('/.trash/', $dir->getName());
        
        // 2 entries exist: do-not-remove.txt & possibly .svn
        $this->assertTrue(2 >= $dir->entries()->size());
      }
    }

    /**
     * Test retrieving the "htdocs" directory which is not empty.
     *
     */
    #[@test]
    public function htdocsDir() {
      $this->conn->connect();
      with ($r= $this->conn->rootDir()); {
        $this->assertTrue($r->hasDir('htdocs'));
        $dir= $r->getDir('htdocs');
        $this->assertClass($dir, 'peer.ftp.FtpDir');
        $this->assertEquals('/htdocs/', $dir->getName());
        $this->assertNotEquals(0, $dir->entries()->size());
      }
    }

    /**
     * Test checking for a non-existant directory
     *
     */
    #[@test]
    public function nonExistantDir() {
      $this->conn->connect();
      $this->assertFalse($this->conn->rootDir()->hasDir(':DOES_NOT_EXIST'));
    }

    /**
     * Test retrieving a non-existant directory raises an exception.
     *
     */
    #[@test, @expect('io.FileNotFoundException')]
    public function getNonExistantDir() {
      $this->conn->connect();
      $this->conn->rootDir()->getDir(':DOES_NOT_EXIST');
    }

    /**
     * Test retrieving the "htdocs/index.html" file
     *
     */
    #[@test]
    public function indexHtml() {
      $this->conn->connect();
      with ($htdocs= $this->conn->rootDir()->getDir('htdocs')); {
        $this->assertTrue($htdocs->hasFile('index.html'));
        $index= $htdocs->getFile('index.html');
        $this->assertClass($index, 'peer.ftp.FtpFile');
        $this->assertEquals('/htdocs/index.html', $index->getName());
      }
    }

    /**
     * Test retrieving the "htdocs/index.html" file
     *
     */
    #[@test]
    public function whitespacesHtml() {
      $this->conn->connect();
      with ($htdocs= $this->conn->rootDir()->getDir('htdocs')); {
        $this->assertTrue($htdocs->hasFile('file with whitespaces.html'));
        $file= $htdocs->getFile('file with whitespaces.html');
        $this->assertClass($file, 'peer.ftp.FtpFile');
        $this->assertEquals('/htdocs/file with whitespaces.html', $file->getName());
      }
    }

    /**
     * Test checking for a non-existant file
     *
     */
    #[@test]
    public function nonExistantFile() {
      $this->conn->connect();
      $this->assertFalse($this->conn->rootDir()->getDir('htdocs')->hasFile(':DOES_NOT_EXIST'));
    }

    /**
     * Test retrieving a non-existant file raises an exception
     *
     */
    #[@test, @expect('io.FileNotFoundException')]
    public function getNonExistantFile() {
      $this->conn->connect();
      $this->conn->rootDir()->getDir('htdocs')->getFile(':DOES_NOT_EXIST');
    }

    /**
     * Test retrieving a directory with getFile() raises an exception
     *
     */
    #[@test, @expect('lang.IllegalStateException')]
    public function directoryViaGetFile() {
      $this->conn->connect();
      $this->conn->rootDir()->getFile('htdocs');
    }

    /**
     * Test retrieving a file with getDir() raises an exception
     *
     */
    #[@test, @expect('lang.IllegalStateException')]
    public function fileViaGetDir() {
      $this->conn->connect();
      $this->conn->rootDir()->getDir('htdocs')->getDir('index.html');
    }

    /**
     * Test uploading
     *
     */
    #[@test]
    public function uploadFile() {
      $this->conn->connect();

      try {
        $dir= $this->conn->rootDir()->getDir('htdocs');
        $file= $dir->file('name.txt')->uploadFrom(new MemoryInputStream($this->name));
        $this->assertTrue($file->exists());
        $this->assertEquals(strlen($this->name), $file->getSize());
        $file->delete();
      } catch (Throwable $e) {

        // Unfortunately, try { } finally does not exist...
        try {
          $file && $file->delete();
        } catch (IOException $ignored) {
          // Can't really do anything here
        }
        throw $e;
      }
    }

    /**
     * Test renaming a file
     *
     */
    #[@test]
    public function renameFile() {
      $this->conn->connect();

      try {
        $dir= $this->conn->rootDir()->getDir('htdocs');
        $file= $dir->file('name.txt')->uploadFrom(new MemoryInputStream($this->name));
        $file->rename('renamed.txt');
        $this->assertFalse($file->exists(), 'Origin file still exists');

        $file= $dir->file('renamed.txt');
        $this->assertTrue($file->exists(), 'Renamed file does not exist');
        $file->delete();
      } catch (Throwable $e) {

        // Unfortunately, try { } finally does not exist...
        try {
          $file && $file->delete();
        } catch (IOException $ignored) {
          // Can't really do anything here
        }
        throw $e;
      }
    }

    /**
     * Test moving a file
     *
     */
    #[@test]
    public function moveFile() {
      $this->conn->connect();

      try {
        $dir= $this->conn->rootDir()->getDir('htdocs');
        $trash= $this->conn->rootDir()->getDir('.trash');

        $file= $dir->file('name.txt')->uploadFrom(new MemoryInputStream($this->name));
        $file->moveTo($trash);
        $this->assertFalse($file->exists());

        $file= $trash->file('name.txt');
        $this->assertTrue($file->exists());
        $file->delete();
      } catch (Throwable $e) {

        // Unfortunately, try { } finally does not exist...
        try {
          $file && $file->delete();
        } catch (IOException $ignored) {
          // Can't really do anything here
        }
        throw $e;
      }
    }

    /**
     * Test downloading
     *
     */
    #[@test]
    public function downloadFile() {
      $this->conn->connect();

      $m= $this->conn
        ->rootDir()
        ->getDir('htdocs')
        ->getFile('index.html')
        ->downloadTo(new MemoryOutputStream())
      ;

      $this->assertEquals("<html/>\n", $m->getBytes());
    }

    /**
     * Test FtpFile::getInputStream()
     *
     */
    #[@test]
    public function getInputStream() {
      $this->conn->connect();

      $s= $this->conn
        ->rootDir()
        ->getDir('htdocs')
        ->getFile('index.html')
        ->getInputStream()
      ;

      $this->assertEquals("<html/>\n", Streams::readAll($s));
    }

    /**
     * Test FtpFile::getInputStream()
     *
     */
    #[@test]
    public function getInputStreams() {
      $this->conn->connect();
      $dir= $this->conn->rootDir()->getDir('htdocs');

      for ($i= 0; $i < 2; $i++) {
        try {
          $s= $dir->getFile('index.html')->getInputStream();
          $this->assertEquals("<html/>\n", Streams::readAll($s));
        } catch (IOException $e) {
          $this->fail('Round '.($i + 1), $e, NULL);
        }
      }
    }

    /**
     * Test FtpFile::getOutputStream()
     *
     */
    #[@test]
    public function getOutputStream() {
      $this->conn->connect();

      $file= $this->conn->rootDir()->getDir('htdocs')->file('name.txt');
      $s= $file->getOutputStream();
      try {
        $s->write($this->name);
        $s->close();

        $this->assertTrue($file->exists());
        $this->assertEquals(strlen($this->name), $file->getSize());
        $file->delete();
      } catch (Throwable $e) {

        // Unfortunately, try { } finally does not exist...
        try {
          $file && $file->delete();
        } catch (IOException $ignored) {
          // Can't really do anything here
        }
        throw $e;
      }
    }
  }
?>
