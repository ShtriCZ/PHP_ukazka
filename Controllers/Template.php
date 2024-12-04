<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Template extends BaseController
{

    protected $template_model;
    protected $drafts_model;

    public function __construct()
    {
        $this->template_model = new \App\Models\TemplateModel();
        $this->drafts_model = new \App\Models\DraftsModel();
    }

    public function index()
    {
        echo view('templates/header');
        echo view('templates');
        echo view('templates/footer');
    }

    public function new_template()
    {
        echo view('templates/header');
        echo view('new_template');
        echo view('templates/footer');
    }

    public function dashboard()
    {
        echo view('templates/header');
        echo view('dashboard');
        echo view('templates/footer');
    }

    public function download()
    {
        echo view('download');
        echo view('templates/header');
        echo view('templates');
        echo view('templates/footer');
    }

    /*
     * Saves new template into database
     * 
     * @return view
     */

    public function save_template()
    {
        if ($this->request->getMethod() == 'post') {
            $elements = $this->request->getVar('Elements');
            $newData = [
                'type' => $this->request->getVar('Type'),
                'variant' => $this->request->getVar('Variant'),
                'version' => $this->request->getVar('Version'),
                'elements' => $this->adjustNumbering($elements),
                'values' => $this->getValues($elements),
                'status' => 'active',
                'fixed' => $this->getFixed($elements),
            ];

            $this->template_model->saveData($newData);
            echo view('templates/header');
            echo view('templates');
            echo view('templates/footer');
        } else {
            echo view('templates/header');
            echo view('new_template');
            echo view('templates/footer');
        }
    }

    /*
     * Edits existing template
     * 
     * @return view
     */

    public function edit_template()
    {
        if ($this->request->getMethod() == 'post') {
            $elements = $this->request->getVar('Elements');
            $data = [
                'id' => $this->request->getVar('Id'),
                'type' => $this->request->getVar('Type'),
                'variant' => $this->request->getVar('Variant'),
                'version' => $this->request->getVar('Version'),
                'elements' => $this->adjustNumbering($elements),
                'values' => $this->getValues($elements),
                'status' => $this->request->getVar('Status'),
                'fixed' => $this->getFixed($elements),
            ];

            $this->template_model->replaceData($data);
            echo view('templates/header');
            echo view('templates');
            echo view('templates/footer');
        } else {
            echo view('templates/header');
            echo view('new_template');
            echo view('templates/footer');
        }
    }

    /**
     * Correct numbering for tyres and couplings so they are in order in case we deleted them
     * 
     * @param type $inputString data where we want to change numbering
     * @return string adjusted data
     */
    function adjustNumbering($inputString)
    {
        // Split the input string by ','
        $elements = explode(',', $inputString);
        $result = "";
        $couplCount = 0;
        $couplDCount = 0;
        $couplSCount = 0;

        // Iterate through each element
        for ($i = 0; $i < count($elements); $i++) {
            // Correct numbering for tyres and rim size
            if (str_contains($elements[$i], 'TyreSize') || str_contains($elements[$i], 'RimSizeIncludingOffSet')) {
                $data = explode('-', $elements[$i]);
                $count = 0;
                for ($x = 0; $x < count($elements); $x++) {
                    if ($x <= $i) {
                        if (str_contains($elements[$x], $data[0])) {
                            $lastValue = explode('-', $elements[$x]);
                            if (isset($lastValue[1])) {
                                $count++;
                            }
                        }
                    }
                }
                if ($count !== 0) {
                    $result = $result . $data[0] . '-' . $count . ',';
                } else {
                    $result = $result . $data[0] . ',';
                }
            }
            // Correct numbering for coupling device 
            elseif (str_contains($elements[$i], 'ApprovalNrCouplingDevice')) {
                if ($couplCount === 0) {
                    $result = $result . 'ApprovalNrCouplingDevice,';
                    $couplCount++;
                } else {
                    $result = $result . 'ApprovalNrCouplingDevice' . $couplCount . ',';
                    $couplCount++;
                }
            }
            // Correct numbering for coupling value D
            elseif (str_contains($elements[$i], 'CouplingCharacteristicValueD')) {
                if ($couplDCount === 0) {
                    $result = $result . 'CouplingCharacteristicValueD,';
                    $couplDCount++;
                } else {
                    $result = $result . 'CouplingCharacteristicValueD' . $couplDCount . ',';
                    $couplDCount++;
                }
            }
            // Correct numbering for coupling value S 
            elseif (str_contains($elements[$i], 'CouplingCharacteristicValueS')) {
                if ($couplSCount === 0) {
                    $result = $result . 'CouplingCharacteristicValueS,';
                    $couplSCount++;
                } else {
                    $result = $result . 'CouplingCharacteristicValueS' . $couplSCount . ',';
                    $couplSCount++;
                }
            } elseif ($elements[$i] !== "") {
                $result = $result . $elements[$i] . ',';
            }
        }
        return $result;
    }

    /*
     * Gets values from inputs
     * 
     * @param string $elements names of elements
     * @return string $values values from inputs
     */

    public function getValues($elements)
    {
        $data = explode(",", $elements);
        $values = "";
        for ($i = 0; $i < count($data) - 1; $i++) {
            $values = $values . str_replace(";", "", $this->request->getVar($data[$i])) . ";";
        }
        return $values;
    }

    /*
     * Gets values from
     * 
     * @param string $elements names of elements
     * @return string $values status of elements (fixed or not_fixed)
     */

    public function getFixed($elements)
    {
        $data = explode(",", $elements);
        $values = "";
        for ($i = 0; $i < count($data) - 1; $i++) {
            $values = $values . $this->request->getVar("Fixed" . $data[$i]) . ";";
        }
        return $values;
    }

    public function next_template()
    {
        echo view('templates/header');
        echo view('next_template');
        echo view('templates/footer');
    }

    public function import()
    {
        echo view('templates/header');
        echo view('import_template');
        echo view('templates/footer');
    }

    /*
     * Sets template to inactive
     * 
     * @param int $id id of template
     * @return view
     */

    public function inactive($id)
    {
        $this->template_model->deactivate($id);

        return redirect()->to('templates');
    }

    /*
     * Sets template to active
     * 
     * @param int $id id of template
     * @return view
     */

    public function active($id)
    {
        $this->template_model->activate($id);

        return redirect()->to('templates');
    }

    /*
     * Delete template
     * 
     * @param int $id id of template
     * @return view
     */

    public function delete($id)
    {

        $this->template_model->delete($id);

        return redirect()->to('templates');
    }

    /*
     * Delete from draft
     * 
     * @param int $id id of draft
     * @return view
     */

    public function delete_draft($id)
    {

        $this->drafts_model->delete($id);

        return redirect()->to('drafts');
    }

    public function copy()
    {
        echo view('templates/header');
        echo view('new_template');
        echo view('templates/footer');
    }

    public function update_database()
    {
        echo view('templates/header');
        echo view('templates');
        echo view('templates/footer');
    }

    public function draft()
    {
        echo view('templates/header');
        echo view('drafts');
        echo view('templates/footer');
    }
}
