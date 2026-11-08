<?php
namespace Modules\Diagnostics\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['code','name','parent_id','active','container','collection_instructions','diagnostic_test_category_id','diagnostic_specimen_id','department_id','test_type','tat_minutes','requires_fasting','requires_external','requires_approval','version','diagnostic_test_id','data_type','unit','sort_order','required','diagnostic_test_parameter_id','low','high','applies_to','effective_from','effective_to','source','branch_id','employee_id','role'])]
class DiagnosticBranchTest extends Model
{
    protected $table = 'diagnostic_branch_tests';
    protected $casts = ['active' => 'boolean', 'requires_fasting' => 'boolean', 'requires_external' => 'boolean', 'requires_approval' => 'boolean', 'required' => 'boolean', 'low' => 'decimal:4', 'high' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];
    public function category(): BelongsTo { return $this->belongsTo(DiagnosticTestCategory::class, 'diagnostic_test_category_id'); }
    public function specimen(): BelongsTo { return $this->belongsTo(DiagnosticSpecimen::class, 'diagnostic_specimen_id'); }
    public function department(): BelongsTo { return $this->belongsTo(\Modules\HospitalOperations\Models\Department::class); }
    public function test(): BelongsTo { return $this->belongsTo(DiagnosticTest::class, 'diagnostic_test_id'); }
    public function parameter(): BelongsTo { return $this->belongsTo(DiagnosticTestParameter::class, 'diagnostic_test_parameter_id'); }
    public function parameters(): HasMany { return $this->hasMany(DiagnosticTestParameter::class, 'diagnostic_test_id'); }
    public function branches(): HasMany { return $this->hasMany(DiagnosticBranchTest::class, 'diagnostic_test_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Foundation\Models\Branch::class); }
    public function employee(): BelongsTo { return $this->belongsTo(\Modules\Administration\Models\Employee::class); }
}
