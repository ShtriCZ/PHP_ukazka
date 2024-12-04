<?= $this->extend("layouts/app") ?>

<?= $this->section("body") ?>
<?php
// Get data.
$import = filter_input(INPUT_POST, 'Import');
$id = filter_input(INPUT_POST, 'Id');
$type = filter_input(INPUT_POST, 'Type');
$variant = filter_input(INPUT_POST, 'Variant');
$version = filter_input(INPUT_POST, 'Version');
$elements = explode(",", filter_input(INPUT_POST, 'Elements'));
$vals = filter_input(INPUT_POST, 'Values');
// Get values in case we are importing data from xml file
if (isset($vals)) {
    $values = explode(";", $vals);
}
$status = filter_input(INPUT_POST, 'Status');

$data = array();
$fixed = array();
$db = \Config\Database::connect();
$query = $db->query('SELECT * FROM tbl_template WHERE id = "' . $id . '"');
$results = $query->getResult();
// If we don't have predefined values from imported file
if (!isset($vals)) {
    // Get values and whether it's fixed or not
    foreach ($results as $row) {
        $parsed_data = explode(";", $row->values);
        $fix = explode(";", $row->fixed);
        $el = explode(",", $row->elements);
        for ($i = 0; $i < count($parsed_data) - 1; $i++) {
            foreach ($elements as $element) {
                if ($element === $el[$i]) {
                    if (isset($parsed_data[$i])) {
                        $data[$element] = $parsed_data[$i];
                    }
                    if (isset($fix[$i])) {
                        $fixed[$element] = $fix[$i];
                    } else {
                        $fixed[$element] = 'fixed';
                    }
                }
            }
        }
    }
} else {
    // Map values to elements
    for ($i = 0; $i < count($elements) - 1; $i++) {
        if (isset($values[$i])) {
            $data[$elements[$i]] = $values[$i];
        }
    }
}
$data['Type'] = $type;
$data['Variant'] = $variant;
$data['Version'] = $version;

$filePathXSD = 'ereg-association.eu_media_2884_version-182-initial-vehicle-information-xsd-scheme.xsd';
// Check if the file exists
if (file_exists($filePathXSD)) {
    // Read the file into an array of lines
    $lines = file($filePathXSD, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines !== false) {
        // Loop through each line and parse it using the specified delimiter
        $group = "";
        $element = "";
        $tyres = array();
        $coupling = array();
        foreach ($lines as $line) {
            if (str_contains($line, '<xs:complexType name="')) {
                $group = str_replace('<xs:complexType name="', '', $line);
                $group = str_replace('">', '', $group);
            } elseif (str_contains($line, '<xs:element name="')) {
                $element = explode('"', $line)[1];
                foreach ($elements as $el) {
                    if ($el === $element) {
                        $groups[$element] = $group;
                    } elseif (preg_replace('/\d+$/', '', $el) === $element) {
                        $groups[$el] = $group;
                    } elseif (str_contains($el, 'TyreSize') || str_contains($el, 'RimSizeIncludingOffSet')) {
                        $tyres[$el] = $el;
                    } elseif (str_contains($el, 'ApprovalNrCouplingDevice') && $el !== 'ApprovalNrCouplingDevice') {
                        $coupling[$el] = $el;
                    }
                }
            }
        }
    }
}
helper('form');
// Different url if it's new form or editing old one.
if (isset($id) && $id !== '' && $status !== 'copy') {
    echo form_open('edit_template', 'id="data_form"');
} else {
    echo form_open('save_template', 'id="data_form"');
}
echo '<input type="hidden" name="Id" id="Id" value="' . $id . '">'
    . '<input type="hidden" name="Type" id="Type" value="' . $type . '">'
    . '<input type="hidden" name="Variant" id="Variant" value="' . $variant . '">'
    . '<input type="hidden" name="Version" id="Version" value="' . $version . '">'
    . '<input type="hidden" name="Elements" id="Elements" value="' . filter_input(INPUT_POST, 'Elements') . ',">';
if ($status === 'copy') {
    echo '<input type="hidden" name="Status" id="Status" value="active">';
} else {
    echo '<input type="hidden" name="Status" id="Status" value="' . $status . '">';
}
echo '<table style="width:100%;"  id="data_table">';
$addAxle = false;
$axles = array();
$axles_elements = array();
$axles_desc = array();
$axles_values = array();
$axles_fixed = array();
$elements_data = "";
$coupl_device = 1;
foreach ($groups as $element => $values) {
    if (trim($values) === 'AxleGroup' || trim($values) === 'TyreAxleGroup') {
        $query = $db->query('SELECT * FROM tbl_description WHERE field = "' . preg_replace('/\d+$/', '', $element) . '"');
    } else {
        $query = $db->query('SELECT * FROM tbl_description WHERE field = "' . $element . '"');
    }
    $results = $query->getResult();
    foreach ($results as $row) {
        $line = $row->description;
        if (substr($line, -1) === '.') {
            $line = rtrim($line, '.');
        }
        if ((trim($values) === 'AxleGroup' || trim($values) === 'TyreAxleGroup')) {
            $addAxle = true;
            $axles_elements[preg_replace('/\d+$/', '', $element)] = preg_replace('/\d+$/', '', $element);
            if (isset($data[$element])) {
                $axles[$element] = $data[$element];
            }
            $axles_desc[preg_replace('/\d+$/', '', $element)] = $line;
            if (isset($fixed[$element])) {
                $axles_fixed[$element] = $fixed[$element];
            }
            if (($row->values) !== "") {
                $values = explode("\n", $row->values);
                $axle_val = "";
                foreach ($values as $value) {
                    $value = trim($value);
                    if (str_contains($value, "=")) {
                        $separator = "=";
                    } elseif (str_contains($value, ":")) {
                        $separator = ":";
                    } else {
                        $separator = " ";
                    }
                    $val = explode($separator, $value);
                    $axle_val = $axle_val . trim($val[0]) . ";";
                    for ($x = 1; $x < count($val); $x++) {
                        $axle_val = $axle_val . trim($val[$x]);
                        if ($separator === " ") {
                            $axle_val = $axle_val . " ";
                        }
                        if (isset($data[$element]) && trim($val[0]) === $data[$element]) {
                            $axle_val = $axle_val . ';selected';
                        }
                        $axle_val = $axle_val . ';;';
                    }
                }
                $axles_values[$element] = $axle_val;
            } else {
                $axles_values[$element] = "";
            }
            if (isset($fixed[$element])) {
                $axles_fixed[$element] = $fixed[$element];
            }
        } else {
            $elements_data = $elements_data . $element . ",";
            // Print description.
            echo '<tr><td><label for="' . $element . '">' . $line . ':</label></td><td>';
            // If element has predefined values add them into select.
            if (($row->values) !== "") {
                echo "<select class='form-control' name='" . $element . "' id='" . $element . "'>";
                $values = explode("\n", $row->values);
                foreach ($values as $value) {
                    $value = trim($value);
                    if (str_contains($value, "=")) {
                        $separator = "=";
                    } elseif (str_contains($value, ":")) {
                        $separator = ":";
                    } else {
                        $separator = " ";
                    }
                    $val = explode($separator, $value);
                    echo "<option value='" . trim($val[0]) . "'";
                    if (isset($data[$element]) && trim($val[0]) === $data[$element]) {
                        echo "selected='selected'";
                    }
                    echo ">";
                    for ($x = 1; $x < count($val); $x++) {
                        echo trim($val[$x]);
                        if ($separator === " ") {
                            echo " ";
                        }
                    }
                    echo "</option>";
                }
                echo "</select>";
            } else {
                echo '<input class="form-control" type="text" name="' . $element . '" id="' . $element . '" value="';
                if ($element === 'VersionDateIVI') {
                    $currentDateTime = date('Y-m-d\TH:i:s');
                    echo $currentDateTime;
                } elseif (isset($data[$element])) {
                    echo $data[$element];
                } elseif ($element === 'AxleGroupNumber' || $element === 'NumberOfAxles') {
                    echo '1';
                }
                echo '">';
            }
            // Whether element has fixed value or not.
            echo '</td>'
                . '<td style="text-align:center;">';
            if ($element === 'ApprovalNrCouplingDevice') {
                echo '<button type="button" onclick="addButton()">Add</button><br>';
            }
            echo '<input id="fixed' . $element . '" type="radio" name="Fixed' . $element . '" value="fixed"';

            if (isset($fixed[$element]) && $fixed[$element] !== "") {
                if ($fixed[$element] === "fixed") {
                    echo "checked";
                }
            } else {
                echo "checked";
            }
            echo '><label for="fixed' . $element . '" style="font-weight: normal;"> Fixed</label>';
            echo ' <input id="not_fixed' . $element . '" type="radio" name="Fixed' . $element . '" value="not_fixed"';
            if (isset($fixed[$element]) && $fixed[$element] === "not_fixed") {
                echo "checked";
            }
            echo '><label for="not_fixed' . $element . '" style="font-weight: normal;"> Not Fixed</label></td></tr>';
        }
        if ($addAxle === true) {
            $addAxle = false;
            echo '<tbody id="inputContainer"></tbody>';
        }
    }
}
echo '</table><br><input type="submit" style="float: right;" class="btn btn-success" value="Save"></form>';
if (isset($import)) {
    echo '<button onclick="goBack()" class="back-button">Back</button>';
} else {
    echo form_open('new_template');
    echo '<input type="hidden" name="name" id="name" value="' . $id . ";" . $type . ";" . $variant . ";" . $version . ";" . filter_input(INPUT_POST, 'Elements') . ";" . $status . '">'
        . '<input type="submit" style="float: left;" value="Back"></form>';
}
?>
<script>
    /*
     * Back button
     */
    function goBack() {
        window.history.back();
    }

    /*
     * Counts all inputs with specific name
     *
     * @param string specificString name of input          
     * @return int number of found inputs   
     */
    function countInputsContainingString(specificString) {
        var form = document.getElementById('data_form');
        // Select all input elements within the form whose 'name' attribute contains the specific string
        var inputs = form.querySelectorAll('input[name^="' + specificString + '"]');
        var count = inputs.length;
        return count;
    }

    /*
     * Returns value of radio button
     * 
     * @param string name name of radio button
     * @return string value of the radio button
     */
    function getRadioValue(name) {
        var radioButtons = document.getElementsByName(name);
        var selectedValue = null;
        radioButtons.forEach(function(radio) {
            if (radio.checked) {
                selectedValue = radio.value;
            }
        });
        if (selectedValue !== null) {
            return selectedValue;
        } else {
            return 'fixed';
        }
    }

    /*
     * Adds inputs for more coupling device options 
     */
    function addButton() {
        var elements = "";
        var inputElement = document.getElementById('Elements');
        if (inputElement) {
            elements = inputElement.value;
        } else {
            elements = '<?php echo $elements_data; ?>';
        }
        var data = <?php echo json_encode($data); ?>;
        var input1 = document.getElementsByName('ApprovalNrCouplingDevice')[0]; // Assuming there's only one ApprovalNrCouplingDevice in the form
        var input2 = document.getElementsByName('CouplingCharacteristicValueD')[0]; // Assuming there's only one ApprovalNrCouplingDevice in the form
        var input3 = document.getElementsByName('CouplingCharacteristicValueS')[0]; // Assuming there's only one ApprovalNrCouplingDevice in the form
        var count = countInputsContainingString('ApprovalNrCouplingDevice');
        // Create a new input element
        var newCouplDevice = document.createElement('input');
        newCouplDevice.type = 'text';
        newCouplDevice.name = 'ApprovalNrCouplingDevice' + count; // Set a name for the new input
        newCouplDevice.id = 'ApprovalNrCouplingDevice' + count;
        if (data['ApprovalNrCouplingDevice' + count]) {
            newCouplDevice.value = data['ApprovalNrCouplingDevice' + count];
        }
        newCouplDevice.classList.add('form-control');
        elements += 'ApprovalNrCouplingDevice' + count + ',';
        // Insert the new input element after the existing input1
        input1.parentNode.appendChild(newCouplDevice);
        var newInput = document.createElement('input');
        newInput.type = 'hidden';
        newInput.name = 'FixedApprovalNrCouplingDevice' + count; // Set a name for the new input
        newInput.value = getRadioValue('FixedApprovalNrCouplingDevice');
        input1.parentNode.appendChild(newInput);
        // Create a new input element
        var newCouplD = document.createElement('input');
        newCouplD.type = 'text';
        newCouplD.name = 'CouplingCharacteristicValueD' + count; // Set a name for the new input
        newCouplD.id = 'CouplingCharacteristicValueD' + count;
        if (data['CouplingCharacteristicValueD' + count]) {
            newCouplD.value = data['CouplingCharacteristicValueD' + count];
        }
        newCouplD.classList.add('form-control');
        elements += 'CouplingCharacteristicValueD' + count + ',';
        // Insert the new input element after the existing input1
        input2.parentNode.appendChild(newCouplD);
        // Create a new input element
        var newCouplS = document.createElement('input');
        newCouplS.type = 'text';
        newCouplS.name = 'CouplingCharacteristicValueS' + count; // Set a name for the new input
        newCouplS.id = 'CouplingCharacteristicValueS' + count;
        if (data['CouplingCharacteristicValueS' + count]) {
            newCouplS.value = data['CouplingCharacteristicValueS' + count];
        }
        newCouplS.classList.add('form-control');
        elements += 'CouplingCharacteristicValueS' + count + ',';
        // Insert the new input element after the existing input1
        input3.parentNode.appendChild(newCouplS);

        // Create a remove button for the inputs
        var removeButton = document.createElement('button');
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', function() {
            // Remove the TyreSize and RimSizeIncludingOffSet inputs
            newCouplDevice.remove();
            newCouplD.remove();
            newCouplS.remove();
            removeButton.remove();
            updateElements('ApprovalNrCouplingDevice' + count, 'CouplingCharacteristicValueD' + count, 'CouplingCharacteristicValueS' + count);
        });
        input1.parentNode.appendChild(removeButton);

        var inputElement = document.getElementById('Elements');
        if (inputElement) {
            inputElement.value = elements;
        } else {

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'Elements'; // Use an array if inputs are part of a form
            input.id = 'Elements';
            input.value = elements;
            input3.parentNode.appendChild(input);
        }
    }

    /*
     * Adds more tyre options
     * 
     * @param int i number of tyre
     */
    function addTyre(i) {
        var elements = "";
        var inputElement = document.getElementById('Elements');
        if (inputElement) {
            elements = inputElement.value;
        } else {
            elements = '<?php echo $elements_data; ?>';
        }
        var data = <?php echo json_encode($data); ?>;
        var input1 = document.getElementsByName('TyreSize' + i)[0]; // Assuming there's only one ApprovalNrCouplingDevice in the form
        var input2 = document.getElementsByName('RimSizeIncludingOffSet' + i)[0]; // Assuming there's only one ApprovalNrCouplingDevice in the form
        var count = countInputsContainingString('TyreSize' + i);

        // Create a new input element for TyreSize
        var newInputTyreSize = document.createElement('input');
        newInputTyreSize.type = 'text';
        newInputTyreSize.name = 'TyreSize' + i + '-' + count; // Set a name for the new input
        newInputTyreSize.id = 'TyreSize' + i + '-' + count;
        if (data['TyreSize' + i + '-' + count]) {
            newInputTyreSize.value = data['TyreSize' + i + '-' + count];
        }
        newInputTyreSize.classList.add('form-control');
        input1.parentNode.appendChild(newInputTyreSize);

        // Create a new input element for RimSizeIncludingOffSet
        var newInputRimSize = document.createElement('input');
        newInputRimSize.type = 'text';
        newInputRimSize.name = 'RimSizeIncludingOffSet' + i + '-' + count; // Set a name for the new input
        newInputRimSize.id = 'RimSizeIncludingOffSet' + i + '-' + count;
        if (data['RimSizeIncludingOffSet' + i + '-' + count]) {
            newInputRimSize.value = data['RimSizeIncludingOffSet' + i + '-' + count];
        }
        newInputRimSize.classList.add('form-control');
        input2.parentNode.appendChild(newInputRimSize);

        // Create a remove button for the inputs
        var removeButton = document.createElement('button');
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', function() {
            // Remove the TyreSize and RimSizeIncludingOffSet inputs
            newInputTyreSize.remove();
            newInputRimSize.remove();
            removeButton.remove();
            updateElements('TyreSize' + i + '-' + count, 'RimSizeIncludingOffSet' + i + '-' + count);
        });
        input1.parentNode.appendChild(removeButton);

        // Update the elements list
        elements += 'TyreSize' + i + '-' + count + ',';
        elements += 'RimSizeIncludingOffSet' + i + '-' + count + ',';

        // Update the Elements input field value
        if (inputElement) {
            inputElement.value = elements;
        } else {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'Elements'; // Use an array if inputs are part of a form
            input.id = 'Elements';
            input.value = elements;
            input2.parentNode.appendChild(input);
        }
    }

    function updateElements(tyre, rim, coupl) {
        var input2 = document.getElementsByName(rim)[0]; // Assuming there's only one ApprovalNrCouplingDevice in the form
        var inputElement = document.getElementById('Elements');
        var elements = inputElement.value.split(',').filter(Boolean);
        var newElements = "";
        elements.forEach((element) => {
            if (element !== tyre && element !== rim && element !== coupl) {
                newElements += element + ",";
            }
        });
        if (inputElement) {
            inputElement.value = newElements;
        } else {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'Elements'; // Use an array if inputs are part of a form
            input.id = 'Elements';
            input.value = newElements;
            input2.parentNode.appendChild(input);
        }
    }

    /*
     * Adds axles based on number of axles
     */
    function addInputs() {
        var container = document.getElementById('inputContainer');
        if (!container) {
            return;
        }
        var button = document.getElementById('NumberOfAxles');
        if (button) {
            var numberOfInputs = button.value;
        } else {
            numberOfInputs = 1;
        }
        // Clear previous inputs
        container.innerHTML = '';
        // Create new inputs based on the specified number
        var axle = <?php echo json_encode($axles); ?>;
        var axle_element = <?php echo json_encode($axles_elements); ?>;
        var axle_desc = <?php echo json_encode($axles_desc); ?>;
        var axle_values = <?php echo json_encode($axles_values); ?>;
        var axle_fixed = <?php echo json_encode($axles_fixed); ?>;
        var elements = '<?php echo $elements_data; ?>';
        for (var i = 0; i < numberOfInputs; i++) {
            for (var key in axle_element) {
                if (i === numberOfInputs - 1 && key === 'AxleSpacing') {
                    continue;
                }
                elements += key + i + ",";
                var row = document.createElement('tr');
                var cell = document.createElement('td');
                if (axle_desc[key]) {
                    var label = document.createElement('label');
                    label.textContent = axle_desc[key] + ' (' + (i + 1) + '):'; // Use an array if inputs are part of a form
                    label.for = key;
                    cell.appendChild(label);
                    row.appendChild(cell);
                }
                if (axle_values[key + 0] === "" || axle_values[key] === "") {
                    var cell = document.createElement('td');
                    var input = document.createElement('input');
                    input.type = 'text';
                    input.classList.add('form-control');
                    input.name = key + i; // Use an array if inputs are part of a form
                    if (axle[key + i]) {
                        input.value = axle[key + i];
                    } else if (key === 'AxleNumber') {
                        input.value = i + 1;
                    } else if (key === 'TyreNumber') {
                        input.value = i + 1;
                    }
                    cell.appendChild(input);
                    row.appendChild(cell);
                } else {
                    var cell = document.createElement('td');
                    var select = document.createElement('select');
                    select.id = key + i;
                    select.name = key + i;
                    select.classList.add('form-control');
                    var values = "";
                    if (axle_values[key + i]) {
                        values = axle_values[key + i].split(';;');
                    } else if (axle_values[key + '0']) {
                        values = axle_values[key + '0'].split(';;');
                    }
                    for (var x = 0; x < values.length - 1; x++) {
                        var val = values[x].split(';');
                        // Create options and add them to the select element
                        var option = document.createElement('option');
                        option.value = val[0]; // Set value for option
                        option.text = val[1]; // Set text displayed in the option
                        if (val.length > 2) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    }

                    cell.appendChild(select);
                    row.appendChild(cell);
                }


                var cell = document.createElement('td');
                if (key === 'TyreSize') {
                    var button = document.createElement('button');
                    button.type = 'button';
                    (function(index) {
                        button.onclick = function() {
                            addTyre(index);
                        };
                    })(i);
                    button.innerHTML = 'Add';
                    cell.appendChild(button);

                }
                cell.style = "text-align:center;";
                var input = document.createElement('input');
                input.type = 'radio';
                input.id = 'fixed' + key + i;
                input.name = 'Fixed' + key + i; // Use an array if inputs are part of a form
                input.value = 'fixed';
                if (!axle_fixed[key + i] || axle_fixed[key + i] !== 'not_fixed') {
                    input.checked = true;
                }
                cell.appendChild(input);
                row.appendChild(cell);
                var label = document.createElement('label');
                label.style = "font-weight: normal;";
                label.setAttribute('for', 'fixed' + key + i);
                label.textContent = ' Fixed'; // Use an array if inputs are part of a form
                label.for = 'fixed' + key + i;
                cell.appendChild(label);
                row.appendChild(cell);
                var input = document.createElement('input');
                input.type = 'radio';
                input.id = 'not_fixed' + key + i;
                input.name = 'Fixed' + key + i; // Use an array if inputs are part of a form
                input.value = 'not_fixed';
                if (axle_fixed[key + i] && axle_fixed[key + i] === 'not_fixed') {
                    input.checked = true;
                }
                cell.appendChild(input);
                row.appendChild(cell);
                var label = document.createElement('label');
                label.style = "font-weight: normal;";
                label.setAttribute('for', 'not_fixed' + key + i);
                label.textContent = 'Not Fixed'; // Use an array if inputs are part of a form
                label.for = 'not_fixed' + key + i;
                cell.appendChild(label);
                row.appendChild(cell);
                container.appendChild(row);
            }
        }
        var inputElement = document.getElementById('Elements');
        if (inputElement) {
            inputElement.value = elements;
        } else {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'Elements'; // Use an array if inputs are part of a form
            input.id = 'Elements';
            input.value = elements;
            container.appendChild(input);
        }
    }
    var button = document.getElementById('NumberOfAxles');
    if (button) {
        // Listen to the change event of the input field
        button.addEventListener('change', addInputs);
    }
    // Initially add inputs based on the default value
    addInputs();

    var tyres = <?php echo json_encode($tyres); ?>;
    for (var tyre in tyres) {
        if (tyre.includes('-') && tyre.includes('TyreSize')) {
            var number = (tyre.split('-')[0]).split('TyreSize')[1];
            addTyre(number);
        }
    }

    var coupling = <?php echo json_encode($coupling); ?>;
    for (var coupl in coupling) {
        addButton();

    }
</script>
<?= $this->endSection() ?>