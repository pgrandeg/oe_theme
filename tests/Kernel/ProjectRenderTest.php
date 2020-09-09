<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_theme\Kernel;

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\oe_content_entity_organisation\Entity\Organisation;
use Drupal\Tests\oe_theme\PatternAssertions\FieldListAssert;
use Drupal\Tests\oe_theme\PatternAssertions\ListItemAssert;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the project rendering.
 */
class ProjectRenderTest extends ContentRenderTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'field_group',
    'datetime_range',
    'entity_reference_revisions',
    'link',
    'image',
    'inline_entity_form',
    'oe_content_reference_code_field',
    'oe_content_featured_media_field',
    'oe_content_departments_field',
    'oe_content_entity',
    'oe_content_entity_contact',
    'oe_content_entity_organisation',
    'oe_content_project',
    'composite_reference',
    'oe_theme_content_project',
    'options',
    'oe_time_caching',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_contact');
    $this->installEntitySchema('oe_organisation');
    $this->installConfig([
      'oe_content_reference_code_field',
      'oe_content_featured_media_field',
      'oe_content_departments_field',
      'oe_content_project',
      'oe_content_entity_organisation',
      'oe_theme_content_project',
      'address',
    ]);

    module_load_include('install', 'oe_theme_content_project');
    oe_theme_content_project_install();

    module_load_include('install', 'oe_content');
    oe_content_install();

    // Set current user to UID 1, so that by default we can access everything.
    $account = User::load(1);
    $this->setCurrentUser($account);
  }

  /**
   * Test an event being rendered as a teaser.
   */
  public function testProjectTeaser(): void {
    $file = file_save_data(file_get_contents(drupal_get_path('theme', 'oe_theme') . '/tests/fixtures/example_1.jpeg'), 'public://example_1.jpeg');
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'test image',
      'oe_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Alt',
      ],
    ]);
    $media->save();

    $coordinator = Organisation::create([
      'name' => 'Coordinator 1',
      'bundle' => 'oe_stakeholder',
    ]);
    $coordinator->set('oe_address', [
      'country_code' => 'BE',
      'locality' => 'Brussels',
      'postal_code' => 1000,
      'address_line1' => 'The street',
    ]);
    $coordinator->save();

    $participant = Organisation::create([
      'name' => 'Participant 1',
      'bundle' => 'oe_stakeholder',
    ]);
    $participant->set('oe_address', [
      'country_code' => 'BE',
      'locality' => 'Brussels',
      'postal_code' => 1000,
      'address_line1' => 'The street',
    ]);
    $participant->save();

    $values = [
      'type' => 'oe_project',
      'title' => 'Project 1',
      'oe_subject' => 'http://data.europa.eu/uxp/1005',
      'oe_author' => 'http://publications.europa.eu/resource/authority/corporate-body/ACJHR',
      'oe_content_owner' => 'http://publications.europa.eu/resource/authority/corporate-body/ABEC',
      'oe_project_locations' => [
        [
          'country_code' => 'BE',
          'locality' => 'Brussels',
          'postal_code' => 1000,
        ],
      ],
      'body' => 'The body text',
      'oe_teaser' => 'The teaser text',
      'oe_project_coordinators' => [
        [
          'target_id' => $coordinator->id(),
          'target_revision_id' => $coordinator->getRevisionId(),
        ],
      ],
      'oe_project_participants' => [
        [
          'target_id' => $participant->id(),
          'target_revision_id' => $participant->getRevisionId(),
        ],
      ],
      'oe_featured_media' => [
        [
          'target_id' => $media->id(),
          'target_revision_id' => $media->getRevisionId(),
        ],
      ],
      'status' => 1,
    ];

    $node = Node::create($values);
    $node->save();

    $build = $this->nodeViewBuilder->view($node, 'teaser');
    $html = $this->renderRoot($build);
    $assert = new ListItemAssert();

    $expected_values = [
      'title' => 'Project 1',
      'url' => '/node/1',
      'description' => 'The teaser text',
      'meta' => NULL,
      'image' => [
        'src' => 'example_1.jpeg',
        'alt' => '',
      ],
      'date' => NULL,
    ];
    $assert->assertPattern($expected_values, $html);
    $assert->assertVariant('thumbnail_secondary', $html);

    // Project teaser description should contain a field list pattern.
    $crawler = new Crawler($html);
    $html = $crawler->filter('dl.ecl-description-list')->parents()->html();
    $assert = new FieldListAssert();
    $expected_values = [
      'items' => [
        [
          'label' => 'Project locations',
          'body' => '1000 Brussels, Belgium',
        ],
      ],
    ];
    $assert->assertPattern($expected_values, $html);
    $assert->assertVariant('horizontal', $html);
  }

}
