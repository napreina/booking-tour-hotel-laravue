<?php
namespace Modules\Core\Models;

use App\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attributes extends BaseModel
{
    use SoftDeletes;
    protected $table = 'bravo_attrs';
    protected $fillable = ['name'];
    protected $slugField = 'slug';
    protected $slugFromField = 'name';

    public function terms()
    {
        return $this->hasMany(Terms::class, 'attr_id', 'id')->with(['translations']);
    }
}