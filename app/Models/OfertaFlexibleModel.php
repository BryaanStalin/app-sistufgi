<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class OfertaFlexibleModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_by ??= auth()->id();
            $model->updated_by ??= auth()->id();
        });
        static::updating(function (self $model): void {
            $model->updated_by = auth()->id() ?? $model->updated_by;
        });
        static::deleting(function (self $model): void {
            $model->deleted_by = auth()->id() ?? $model->deleted_by;
            $model->updated_by = auth()->id() ?? $model->updated_by;
            $model->save();
        });
    }
}
