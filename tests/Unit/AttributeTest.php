<?php
 
namespace Backpack\Store\tests\Unit;
 
use Backpack\Store\Tests\TestCase;
use Backpack\Store\app\Events\AttributeSaved;
use Backpack\Store\app\Listeners\AttributeSavedListener;
use Backpack\Store\app\Models\Admin\Attribute as AttributeAdmin;
use Backpack\Store\app\Models\AttributeValue;
 
class AttributeTest extends TestCase
{
  public function test_attribute_saved_listener_creates_values_for_persisted_attribute(): void
  {
    $attribute = new AttributeAdmin();
    $attribute->setTranslation('name', 'ru', 'Крепость');
    $attribute->slug = 'krepost';
    $attribute->type = 'radio';
    $attribute->save();

    $attribute->values_array = json_encode([
      [
        'value' => '25',
        'slug' => '25',
      ],
      [
        'value' => '50',
        'slug' => '50',
      ],
    ], JSON_UNESCAPED_UNICODE);

    (new AttributeSavedListener())->handle(new AttributeSaved($attribute));

    $values = AttributeValue::query()
      ->where('attribute_id', $attribute->id)
      ->orderBy('id')
      ->get();

    $this->assertCount(2, $values);
    $this->assertSame([$attribute->id, $attribute->id], $values->pluck('attribute_id')->all());
    $this->assertSame(['25', '50'], $values->pluck('slug')->all());
    $this->assertSame(['25', '50'], $values->map(fn (AttributeValue $value) => $value->getTranslation('value', 'ru'))->all());
  }

  public function test_attribute_saved_listener_skips_unsaved_attribute(): void
  {
    $attribute = new AttributeAdmin();
    $attribute->setTranslation('name', 'ru', 'Крепость');
    $attribute->slug = 'krepost';
    $attribute->type = 'radio';
    $attribute->values_array = json_encode([
      [
        'value' => '25',
        'slug' => '25',
      ],
    ], JSON_UNESCAPED_UNICODE);

    (new AttributeSavedListener())->handle(new AttributeSaved($attribute));

    $this->assertSame(0, AttributeValue::query()->count());
  }
}
