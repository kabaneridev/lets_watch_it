<?php

namespace Common\Admin\Appearance\Themes;

use Eloquent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * App\CssTheme
 *
 * @property int $id
 * @property int $user_id
 * @property bool $default_dark
 * @property bool $default_light
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin Eloquent
 */
class CssTheme extends Model
{
    protected $guarded = ['id'];
    
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'is_dark' => 'boolean',
        'default_dark' => 'boolean',
        'default_light' => 'boolean',
    ];

    public function setColorsAttribute($value)
    {
        if ($value && is_array($value)) {
            $this->attributes['colors'] = json_encode($value);
        }
    }

    public function getColorsAttribute($value)
    {
        if ($value && is_string($value)) {
            return json_decode($value, true);
        } else {
            return [];
        }
    }
}
