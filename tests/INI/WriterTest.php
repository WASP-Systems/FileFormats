<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Wedeto\FileFormats\INI;


use PHPUnit\Framework\TestCase;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

use Wedeto\IO\Path;
use Wedeto\IO\IOException;

/**
 * @covers Wedeto\FileFormats\INI\Writer
 */
class WriterTest extends TestCase
{
    private $dir;

    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('testdir'));
        $this->dir = vfsStream::url('testdir');
    }

    /**
     * @covers Wedeto\FileFormats\INI\Writer::format
     * @covers Wedeto\FileFormats\INI\Writer::writeParameter
     */
    public function testIniWriterException()
    {
        $cfg = array('sec1' => array('nest1' => array('nest2' => array('nest3' => 1))));
        $file = $this->dir . '/test.ini';
        $this->expectException(\DomainException::class);

        $writer = new Writer();
        $writer->write($cfg, $file);
    }

    /**
     * @covers Wedeto\FileFormats\INI\Writer::write
     * @covers Wedeto\FileFormats\INI\Writer::writeParameter
     */
    public function testIniWriterHierarchical()
    {
        $cfg = array(
            'section1' => array(
                'var1' => array(
                    1, 2, 3, 4
                ),
                'var2' => array(
                    'a' => 'z',
                    'b' => 'y',
                    'c' => 'x'
                ),
            ),
            'section2' => array(
                'var2' => true,
                'var3' => 1,
                'var4' => (float)3.0,
                'var5' => false,
                'var6' => null,
                'var7' => (float)3.5,
            )
        );

        $file = $this->dir . '/test.ini';
        $writer = new Writer();
        $writer->rewrite($cfg, $file);

        $ini = file_get_contents($file);
        $expected_ini = <<<EOT
[section1]
var1[0] = 1
var1[1] = 2
var1[2] = 3
var1[3] = 4
var2[a] = "z"
var2[b] = "y"
var2[c] = "x"

[section2]
var2 = true
var3 = 1
var4 = 3.0
var5 = false
var6 = null
var7 = 3.5

EOT;
        $this->assertEquals($ini, $expected_ini);

        $cfg2 = parse_ini_file($file, true, INI_SCANNER_TYPED);
        $this->assertEquals($cfg, $cfg2);
    }

    /**
     * @covers Wedeto\FileFormats\INI\Writer::write
     * @covers Wedeto\FileFormats\INI\Writer::writeParameter
     */
    public function testIniWriterComments()
    {
        $ini = <<<EOT
;precomment about this file
[sec1]
;a-comment for section 1
;testcomment for section 1
var1 = "value1"
var2 = "value2"

[sec2]
;z-comment for section 2
;testcomment for section 2
var3 = "value3"
var4 = "value4"

[sec4]
var9 = "test"

EOT;
        $ini_expected = <<<EOT
;precomment about this file

[sec1]
;a-comment for section 1
;testcomment for section 1
var1 = "value1"
var2 = "value2"

[sec2]
;testcomment for section 2
;z-comment for section 2
var3 = "value3"
var4 = "value4"

[sec3]
var5 = "value5"

EOT;
        $file = $this->dir . '/test.ini';
        file_put_contents($file, $ini);

        // Read contents
        $cfg = parse_ini_file($file, true, INI_SCANNER_TYPED);
        $this->assertEquals($cfg['sec1']['var1'], 'value1');
        $this->assertEquals($cfg['sec1']['var2'], 'value2');
        $this->assertEquals($cfg['sec2']['var3'], 'value3');
        $this->assertEquals($cfg['sec2']['var4'], 'value4');
        
        $cfg['sec3']['var5'] = 'value5';
        unset($cfg['sec4']);
        $writer = new Writer();
        $writer->rewrite($cfg, $file);

        $ini_out = file_get_contents($file);
        $this->assertEquals($ini_out, $ini_expected);
    }

    public function testRewriteUnwritable()
    {
        $file = $this->dir . '/test.ini';

        $ini = <<<EOT
[sec1]
var = "val"
EOT;
        file_put_contents($file, $ini);
        chmod($file, 0400);

        $data = [
            'sec1' => [
                'var' => 'val',
                'var2' => 'val2'
            ]
        ];

        $writer = new Writer;
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Could not open {$file} for writing");
        $writer->rewrite($data, $file);
    }
}
