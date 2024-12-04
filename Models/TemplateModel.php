<?php

namespace App\Models;

use CodeIgniter\Model;

class TemplateModel extends Model
{

    protected $DBGroup = 'default';
    protected $table = 'tbl_template';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $insertID = 0;
    protected $returnType = 'array';
    protected $useSoftDelete = false;
    protected $protectFields = true;
    protected $allowedFields = [
        "type",
        "variant",
        "version",
        "elements",
        "values",
        "status",
        "fixed"
    ];
    // Dates
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    // Validation
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;
    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /*
     * Activate template
     * 
     * @param id of template
     */
    public function activate($id)
    {
        $builder = $this->select()->where('id', $id);
        $query = $builder->get();
        foreach ($query->getResult() as $row) {
            $data = [
                'id' => $id,
                'type' => $row->type,
                'variant' => $row->variant,
                'version' => $row->version,
                'elements' => $row->elements,
                'values' => $row->values,
                'status' => 'active',
                'fixed' => $row->fixed,
            ];
        }
        $this->replace($data);
    }

    /*
     * Deactivate template
     * 
     * @param id of template
     */
    public function deactivate($id)
    {
        $builder = $this->select()->where('id', $id);
        $query = $builder->get();
        foreach ($query->getResult() as $row) {
            $data = [
                'id' => $id,
                'type' => $row->type,
                'variant' => $row->variant,
                'version' => $row->version,
                'elements' => $row->elements,
                'values' => $row->values,
                'status' => 'inactive',
                'fixed' => $row->fixed,
            ];
        }
        $this->replace($data);
    }

    public function saveData($data)
    {
        $this->save($data);
    }

    public function replaceData($data)
    {
        $this->replace($data);
    }
}
