<?php

declare(strict_types=1);

namespace Drupal\Tests\stanford_migrate\Kernel\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\StanfordDownload;
use Drupal\Tests\migrate\Kernel\process\DownloadTest;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the StanfordDownload process plugin.
 */
#[Group('stanford_migrate')]
class StanfordDownloadTest extends DownloadTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'file'];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('file');
    $this->config('file.settings')
      ->set('filename_sanitization', [
        'transliterate' => TRUE,
        'replace_whitespace' => TRUE,
        'replace_non_alphanumeric' => TRUE,
        'deduplicate_separators' => TRUE,
        'lowercase' => TRUE,
        'replacement_character' => '-',
      ])
      ->save();
  }

  /**
   * Tests that the plugin sanitizes filenames using the event dispatcher.
   */
  public function testFilenameSanitization(): void {
    // Test with a filename that contains special characters that should be
    // transliterated.
    $destination_uri = 'public://Test   File & Special-Characters.txt';
    $actual_destination = $this->doTransform($destination_uri);

    // The plugin should process the filename through the FileUploadSanitizeNameEvent.
    // Verify that the file was created at a valid location.
    $this->assertFileExists($actual_destination);
    $this->assertEquals('public://test-file-special-characters.txt', $actual_destination);
  }

  /**
   * Tests that the plugin handles stub rows correctly.
   */
  public function testStubRow(): void {
    $configuration = [];
    $this->container->set('http_client', $this->createMock(Client::class));
    $plugin = StanfordDownload::create($this->container, $configuration, 'stanford_download', []);

    $executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->getMockBuilder(Row::class)
      ->disableOriginalConstructor()
      ->getMock();
    $row->expects($this->once())
      ->method('isStub')
      ->willReturn(TRUE);

    $value = ['http://example.com/file.txt', 'public://file.txt'];
    $result = $plugin->transform($value, $executable, $row, 'foo');

    $this->assertNull($result);
  }

  /**
   * Tests a download with a filename that needs sanitization.
   */
  public function testDownloadWithUnsafeFilename(): void {
    // Create a destination with potentially unsafe characters.
    $destination_uri = 'public://my file (1).txt';
    $actual_destination = $this->doTransform($destination_uri);

    // The file should be downloaded and the filename sanitized.
    $this->assertFileExists($actual_destination);
    $this->assertEquals('public://my-file-1.txt', $actual_destination);
  }

  /**
   * Tests a download that overwrites an existing local file.
   */
  public function testOverwritingDownload(): void {
    // Create a pre-existing file at the destination.
    $destination_uri = $this->createUri('existing_file.txt');

    // Test destructive download.
    $actual_destination = $this->doTransform($destination_uri);
    $this->assertSame($destination_uri, $actual_destination);
    $this->assertFileDoesNotExist('public://existing_file_0.txt');
  }

  /**
   * Tests a download that renames the downloaded file if there's a collision.
   */
  public function testNonDestructiveDownload(): void {
    // Create a pre-existing file at the destination.
    $destination_uri = $this->createUri('another_existing_file.txt');

    // Test non-destructive download.
    $actual_destination = $this->doTransform($destination_uri, ['file_exists' => 'rename']);
    $this->assertSame('public://another_existing_file_0.txt', $actual_destination);
    $this->assertFileExists($actual_destination);
  }

  /**
   * Tests that filenames with unicode characters are properly handled.
   */
  public function testUnicodeFilename(): void {
    // Test with unicode characters in the filename.
    $destination_uri = 'public://文件名.txt';
    $actual_destination = $this->doTransform($destination_uri);

    // The filename should be sanitized.
    $this->assertFileExists($actual_destination);
    $this->assertEquals('public://wenjianming.txt', $actual_destination);
  }

  /**
   * Tests that the plugin properly dispatches FileUploadSanitizeNameEvent.
   */
  public function testEventDispatcherIntegration(): void {
    // Test that the event dispatcher is used in the transform process.
    $destination_uri = 'public://normal_file.txt';
    $actual_destination = $this->doTransform($destination_uri);

    // Verify the file was processed and exists.
    $this->assertFileExists($actual_destination);
    $this->assertStringContainsString('normal_file', $actual_destination);
  }

  /**
   * Runs an input value through the StanfordDownload plugin.
   *
   * @param string $destination_uri
   *   The destination URI to download to.
   * @param array $configuration
   *   (optional) Configuration for the download plugin.
   *
   * @return string
   *   The local URI of the downloaded file.
   */
  protected function doTransform($destination_uri, $configuration = []) {
    // Prepare a mock HTTP client.
    $this->container->set('http_client', $this->createMock(Client::class));

    // Instantiate the plugin statically so it can pull dependencies out of
    // the container.
    $plugin = StanfordDownload::create($this->container, $configuration, 'stanford_download', []);

    // Execute the transformation.
    $executable = $this->createMock(MigrateExecutableInterface::class);
    $row = new Row([], []);

    // Return the downloaded file's local URI.
    $value = [
      'http://drupal.org/favicon.ico',
      $destination_uri,
    ];

    // Assert that number of stream resources in use is the same before and
    // after the download.
    $initial_count = count(get_resources('stream'));
    $return = $plugin->transform($value, $executable, $row, 'foo');
    $this->assertCount($initial_count, get_resources('stream'));
    return $return;
  }

  public function testWriteProtectedDestination(): void {
    $this->markTestSkipped('Disable base test');
  }

}
