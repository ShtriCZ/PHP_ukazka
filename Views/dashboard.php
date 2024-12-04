<?= $this->extend("layouts/app") ?>

<?= $this->section("body") ?>
<?php
if (isset($_SESSION['vin'])) {
    echo '<div id="notification" style="background-color: #d45d58; color: white; text-align: center; padding: 10px;">Vehicle identification number is not available!</div>';
}
// If id is set get elements from database.
$id = filter_input(INPUT_POST, 'Id');
if (filter_input(INPUT_POST, 'Elements')) {
    $elements = explode(",", filter_input(INPUT_POST, 'Elements'));
} else {
    //echo "<script>location.href='templates';</script>";
    $elements = [];
}
helper('form');
echo form_open('data_send');
echo '<table style="width:100%;">';
$db = \Config\Database::connect();
$data = array();
$fixed = array();
$groups = array();
$rim_values = "";
$row_fixed = "";
$coupl_device = array('ApprovalNrCouplingDevice', 'CouplingCharacteristicValueD', 'CouplingCharacteristicValueS');
if (filter_input(INPUT_POST, 'draft')) {
    $query = $db->query('SELECT * FROM tbl_drafts WHERE id = "' . $id . '"');
    $query_template = $db->query('SELECT * FROM tbl_template WHERE type = "' . filter_input(INPUT_POST, 'Type') . '" AND variant = "' . filter_input(INPUT_POST, 'Variant') . '" AND version = "' . filter_input(INPUT_POST, 'Version') . '"');
    $session = session();
    $session->set('draft_id', $id);
} elseif (isset($_SESSION['draft_id'])) {
    $query = $db->query('SELECT * FROM tbl_drafts WHERE id = "' . $_SESSION['draft_id'] . '"');
    $query_template = $db->query('SELECT * FROM tbl_template WHERE type = "' . filter_input(INPUT_POST, 'Type') . '" AND variant = "' . filter_input(INPUT_POST, 'Variant') . '" AND version = "' . filter_input(INPUT_POST, 'Version') . '"');
    //$session = session();
    //$session->set('draft_id', $_SESSION['draft_id']);
} else {
    $query = $db->query('SELECT * FROM tbl_template WHERE id = "' . $id . '"');
}
$results = $query->getResult();
foreach ($results as $row) {
    $row_fixed = $row->fixed;
    $parsed_data = explode(";", $row->values);
    $elements = explode(',', $row->elements);
    $fix = explode(";", $row->fixed);
    for ($i = 0; $i < count($parsed_data) - 1; $i++) {
        $data[$elements[$i]] = $parsed_data[$i];
        $fixed[$elements[$i]] = $fix[$i];
        if ((str_contains($elements[$i], "TyreSize") || str_contains($elements[$i], "RimSizeIncludingOffSet")) && isset($query_template)) {
            $results_template = $query_template->getResult();
            foreach ($results_template as $row_template) {
                $parsed_data_template = explode(";", $row_template->values);
                $data[$elements[$i]] = $parsed_data_template[$i];
            }
        }
    }
}
$filePath = 'ereg-association.eu_media_2884_version-182-initial-vehicle-information-xsd-scheme.xsd';
// Check if the file exists
if (file_exists($filePath)) {
    // Read the file into an array of lines
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines !== false) {
        // Loop through each line and parse it using the specified delimiter
        $group = "";
        $element = "";
        foreach ($lines as $line) {
            if (str_contains($line, '<xs:complexType name="')) {
                $group = str_replace('<xs:complexType name="', '', $line);
                $group = str_replace('">', '', $group);
            } elseif (str_contains($line, '<xs:element name="')) {
                $element = explode('"', $line)[1];
                foreach ($elements as $el) {
                    if ($el === $element) {
                        $groups[$element] = $group;
                    } elseif (preg_replace('/[0-9-]+$/', '', $el) === $element) {
                        $groups[$el] = $group;
                    }
                }
            }
        }
    }
}
echo '<input type="hidden" name="Id" id="Id" value="' . $id . '">'
    . '<input type="hidden" name="Elements" id="Elements" value="' . implode(',', $elements) . '">';

$alt_tyres = "";
$alt_rims = "";
$alt_coupl = "";
// Get description for each ellement.
foreach ($groups as $element => $values) {
    if (trim($values) === 'AxleGroup' || trim($values) === 'TyreAxleGroup' || str_contains($element, 'ApprovalNrCouplingDevice')) {
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
        $result = array_filter($coupl_device, function ($value) use ($element) {
            return (stripos($element, $value) !== false && strlen($element) > strlen($value)) || ($element === $value);
        });
        if ((isset($fixed[$element]) && $fixed[$element] !== "fixed") || str_contains($element, 'TyreSize') || str_contains($element, 'RimSizeIncludingOffSet')) {
            echo '<tr><td>'
                . '<label for="' . $element . '">' . $line . ':</label></td><td>';
        } elseif ($element === 'ApprovalNrCouplingDevice') {
            echo '<tr><td>'
                . '<label for="' . $element . 'Selected">' . $line . ':</label></td><td>';
        }
        // Printing data
        // If element has predefined values and is not fixed add them to select.
        if ((isset($fixed[$element]) && $fixed[$element] !== "fixed")) {
            if (($row->values) !== "") {
                echo "<select type='text' class='form-control' name='" . $element . "' id='" . $element . "'>";
                $values = explode("\n", $row->values);
                // Go through all values, split value and description and add it to option.
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
                    if (isset($data[$element]) && trim($val[0]) === $data[$element] || trim($val[0]) === filter_input(INPUT_POST, $element)) {
                        echo "selected='selected'";
                    }
                    echo ">";
                    for (
                        $x = 1;
                        $x < count($val);
                        $x++
                    ) {
                        echo trim($val[$x]);
                        if ($separator === " ") {
                            echo " ";
                        }
                    }
                    echo "</option>";
                }
                echo "</select>";
            } else {
                echo '<input type="text" class="form-control" name="' . $element . '" id="' . $element . '" value="';
                if ($element === 'VersionDateIVI') {
                    date_default_timezone_set('Europe/Prague');
                    $currentDateTime = date('Y-m-d\TH:i:s');
                    echo $currentDateTime;
                } elseif (isset($data[$element]) && !filter_input(INPUT_POST, $element)) {
                    echo $data[$element];
                } else {
                    echo filter_input(INPUT_POST, $element);
                }
                echo '">';
            }
            if (isset($fixed[$element]) && $fixed[$element] !== "fixed") {
                echo '</td></tr>';
            }
            // If element has predefined values but is fixed add it to input instead of select.
            // Input for other elements, readonly if it's fixed.
            elseif (isset($fixed[$element]) && $fixed[$element] !== "fixed") {
                echo '<input type="hidden" class="form-control" name="' . $element . '" id="' . $element . '" value="';
                if (isset($data[$element]) && !filter_input(INPUT_POST, $element)) {
                    echo $data[$element];
                } else {
                    echo filter_input(INPUT_POST, $element);
                }
                echo '">';
            }
        } elseif (str_contains($element, 'ApprovalNrCouplingDevice')) {
            if ($element === 'ApprovalNrCouplingDevice' && isset($data['ApprovalNrCouplingDevice']) && isset($data['CouplingCharacteristicValueD']) && isset($data['CouplingCharacteristicValueS'])) {
                echo "<select type='text' class='form-control' name='ApprovalNrCouplingDeviceSelected' id='ApprovalNrCouplingDeviceSelected'>";
                echo '<option value="' . $data['ApprovalNrCouplingDevice'] . ',' . $data['CouplingCharacteristicValueD'] . ',' . $data['CouplingCharacteristicValueS'] . '"';
                if (filter_input(INPUT_POST, 'ApprovalNrCouplingDeviceSelected') === ($data['ApprovalNrCouplingDevice'] . ',' . $data['CouplingCharacteristicValueD'] . ',' . $data['CouplingCharacteristicValueS'])) {
                    echo 'selected=selected';
                }
                echo '>' . $data['ApprovalNrCouplingDevice'];
                echo ' D = ' . $data['CouplingCharacteristicValueD'] . ' S = ' . $data['CouplingCharacteristicValueS'] . '</option>';
                $alt_coupl = $alt_coupl . $data['ApprovalNrCouplingDevice'] . ',' . $data['CouplingCharacteristicValueD'] . ',' . $data['CouplingCharacteristicValueS'] . ';;';
                for ($i = 1; $i < count($groups); $i++) {
                    if (isset($data['ApprovalNrCouplingDevice' . $i]) && isset($data['CouplingCharacteristicValueD' . $i]) && isset($data['CouplingCharacteristicValueS' . $i])) {
                        echo '<option value="' . $data['ApprovalNrCouplingDevice' . $i] . ',' . $data['CouplingCharacteristicValueD' . $i] . ',' . $data['CouplingCharacteristicValueS' . $i] . '"';
                        if (filter_input(INPUT_POST, 'ApprovalNrCouplingDeviceSelected') === ($data['ApprovalNrCouplingDevice' . $i] . ',' . $data['CouplingCharacteristicValueD' . $i] . ',' . $data['CouplingCharacteristicValueS' . $i])) {
                            echo 'selected=selected';
                        }
                        echo '>' . $data['ApprovalNrCouplingDevice' . $i];
                        echo ' D = ' . $data['CouplingCharacteristicValueD' . $i] . ' S = ' . $data['CouplingCharacteristicValueS' . $i] . '</option>';
                        $alt_coupl = $alt_coupl . $data['ApprovalNrCouplingDevice' . $i] . ',' . $data['CouplingCharacteristicValueD' . $i] . ',' . $data['CouplingCharacteristicValueS' . $i] . ';;';
                    }
                }
                echo '</select>';
                echo '<input type="hidden" id="ApprovalNrCouplingDevice"  name="ApprovalNrCouplingDevice" value="' . $data['ApprovalNrCouplingDevice'] . '">';
                echo '<input type="hidden" id="CouplingCharacteristicValueD"  name="CouplingCharacteristicValueD" value="' . $data['CouplingCharacteristicValueD'] . '">';
                echo '<input type="hidden" id="CouplingCharacteristicValueS"  name="CouplingCharacteristicValueS" value="' . $data['CouplingCharacteristicValueS'] . '">';
                for ($i = 1; $i < count($groups); $i++) {
                    if (isset($data['ApprovalNrCouplingDevice' . $i]) && isset($data['CouplingCharacteristicValueD' . $i]) && isset($data['CouplingCharacteristicValueS' . $i])) {
                        echo '<input type="hidden" id="ApprovalNrCouplingDevice' . $i . '"  name="ApprovalNrCouplingDevice' . $i . '" value="' . $data['ApprovalNrCouplingDevice' . $i] . '">';
                        echo '<input type="hidden" id="CouplingCharacteristicValueD' . $i . '"  name="CouplingCharacteristicValueD' . $i . '" value="' . $data['CouplingCharacteristicValueD' . $i] . '">';
                        echo '<input type="hidden" id="CouplingCharacteristicValueS' . $i . '"  name="CouplingCharacteristicValueS' . $i . '" value="' . $data['CouplingCharacteristicValueS' . $i] . '">';
                    }
                }
            }
        } elseif (str_contains($element, 'TyreSize')) {
            echo '<select type="text" class="form-control" name="' . $element . '" id="' . $element . '">';
            foreach ($elements as $el) {
                if (str_contains($el, $element) && isset($data[$el])) {
                    echo '<option value="' . $data[$el] . '">' . $data[$el] . '</option>';
                    $alt_tyres = $alt_tyres . $data[$el] . ';';
                }
            }
            echo '</select>';
            echo '<input type="hidden" class="form-control" name="Values' . $element . '" id="Values' . $element . '" value="';
            foreach ($elements as $el) {
                if (str_contains($el, $element) && isset($data[$el])) {
                    echo $data[$el] . ';';
                }
            }
            echo '">';
            $alt_tyres = $alt_tyres . ';';
        } elseif (str_contains($element, 'RimSizeIncludingOffSet')) {
            echo '<select type="text" class="form-control" name="' . $element . '" id="' . $element . '">';
            echo '</select>';
            echo '<input type="hidden" class="form-control" name="Values' . $element . '" id="Values' . $element . '" value="';
            foreach ($elements as $el) {
                if (str_contains($el, $element) && isset($data[$el])) {
                    $alt_rims = $alt_rims . $data[$el] . ';';
                    echo $data[$el] . ';';
                }
            }
            $alt_rims = $alt_rims . ';';
            echo '">';
        }
    }
}
echo '</table>';
echo '<input type="hidden" name="Alt_tyres" id="Alt_tyres" value="' . $alt_tyres . '">';
echo '<input type="hidden" name="Alt_rims" id="Alt_rims" value="' . $alt_rims . '">';
echo '<input type="hidden" name="Alt_coupl" id="Alt_coupl" value="' . $alt_coupl . '">';
foreach ($groups as $element => $values) {
    if (trim($values) === 'AxleGroup' || trim($values) === 'TyreAxleGroup') {
        $query = $db->query('SELECT * FROM tbl_description WHERE field = "' . preg_replace('/\d+$/', '', $element) . '"');
    } elseif (trim($element) === 'ApprovalNrCouplingDevice' || trim($element) === 'CouplingCharacteristicValueD' || trim($element) === 'CouplingCharacteristicValueS') {
        $query = $db->query('SELECT * FROM tbl_description WHERE field = "' . preg_replace('/\d+$/', '', $element) . '"');
    } else {
        $query = $db->query('SELECT * FROM tbl_description WHERE field = "' . $element . '"');
    }
    $results = $query->getResult();
    foreach ($results as $row) {
        if (str_contains($element, 'ApprovalNrCouplingDevice') || str_contains($element, 'CouplingCharacteristicValueD') || str_contains($element, 'CouplingCharacteristicValueS') || str_contains($element, 'TyreSize') || str_contains($element, 'RimSizeIncludingOffSet')) {
        } elseif (isset($fixed[$element]) && $fixed[$element] === "fixed") {
            echo '<input type="hidden" class="form-control" name="' . $element . '" id="' . $element . '" value="';
            if (isset($data[$element]) && !filter_input(INPUT_POST, $element)) {
                echo $data[$element];
            } else {
                echo filter_input(INPUT_POST, $element);
            }
            echo '">';
        } elseif (($row->values) !== "" && isset($fixed[$element]) && $fixed[$element] === "fixed") {

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
                $value = trim($value);
                $val = explode($separator, $value);
                if (isset($data[$element]) && trim($val[0]) === $data[$element] || trim($val[0]) === filter_input(INPUT_POST, $element)) {
                    $text = "";
                    for (
                        $x = 1;
                        $x < count($val);
                        $x++
                    ) {
                        $text = $text . trim($val[$x]);
                        if ($separator === " ") {
                            $text = $text . " ";
                        }
                    }
                    echo '<input type="hidden" class="form-control" name="' . $element . '_data" id="' . $element . '_data" readonly value="' . trim($text) . '"/>';
                    echo '<input type="hidden" class="form-control" name="' . $element . '" id="' . $element . '" value="' . trim($val[0]) . '"/>';
                }
            }
        }
    }
}
echo '<br><input type="hidden" class="form-control" name="fixed" id="fixed" value="' . $row_fixed . '"/>'
    . '<input type="submit" style="float: right;padding: 10px 25px; font-weight: bold;" class="btn btn-success" value="Next"></form>';
if (isset($_SESSION['draft_id'])) {
    echo '<button style="float: left;padding: 10px 25px; font-weight: bold;" class="btn btn-primary" onclick="location.href = \'' . base_url('drafts') . '\'">Back</button>';
} else {
?>
    <button style="float: left;
            padding: 10px 25px; font-weight: bold;" class="btn btn-primary" onclick="location.href = '<?= base_url('templates') ?>'">Back</button>
<?php } ?>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    /*
     * Function to update RimSizeIncludingOffSet based on TyreSize selection and hidden input values
     * 
     * @param string tyreSelectId TyreSize + id
     * @param string tyreValuesId ValuesTyreSize + id
     * @param string rimSelectId RimSizeIncludingOffSet + id
     * @param string rimValuesId ValuesRimSizeIncludingOffSet + id
     */
    function updateRimSize(tyreSelectId, tyreValuesId, rimSelectId, rimValuesId) {
        const tyreSelect = document.getElementById(tyreSelectId);
        const tyreValuesInput = document.getElementById(tyreValuesId);
        const rimSelect = document.getElementById(rimSelectId);
        const rimValuesInput = document.getElementById(rimValuesId);

        if (tyreSelect && tyreValuesInput && rimSelect && rimValuesInput) {
            // Clear previous options in RimSizeIncludingOffSet
            rimSelect.innerHTML = '';

            // Get the selected tyre size value
            const selectedTyreSize = tyreSelect.value;
            const tyreValues = tyreValuesInput.value.split(';');

            // Get corresponding RimSizeIncludingOffSet values from hidden input
            const rimValues = rimValuesInput.value.split(';');

            // Collect unique tyre sizes and their corresponding rim values
            const uniqueTyreSizes = [];
            const uniqueRimValuesMap = {};

            for (let i = 0; i < tyreValues.length; i++) {
                const tyreSize = tyreValues[i];
                if (!uniqueRimValuesMap[tyreSize]) {
                    uniqueRimValuesMap[tyreSize] = rimValues[i].split('/');
                    uniqueTyreSizes.push(tyreSize);
                } else {
                    uniqueRimValuesMap[tyreSize].push(...rimValues[i].split('/'));
                }
            }

            // Remove duplicates from the rim values for each tyre size
            for (let tyreSize in uniqueRimValuesMap) {
                uniqueRimValuesMap[tyreSize] = [...new Set(uniqueRimValuesMap[tyreSize])];
            }

            // Update the rimSelect based on the currently selected tyre size
            const selectedRimValues = uniqueRimValuesMap[selectedTyreSize] || [];
            selectedRimValues.forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                rimSelect.appendChild(option);
            });
        }
    }

    /*
     * If there are duplicates in TyreSize select remove them
     * 
     * @param string tyreSelectId TyreSize + id
     */
    function removeDuplicateOptions(selectId) {
        const selectElement = document.getElementById(selectId);

        if (selectElement) {
            const uniqueValues = new Set();
            const options = Array.from(selectElement.options);

            options.forEach(option => {
                if (uniqueValues.has(option.value)) {
                    selectElement.removeChild(option);
                } else {
                    uniqueValues.add(option.value);
                }
            });
        } else {
            console.error(`Element with ID '${selectId}' not found.`);
        }
    }

    const tyreSizeInputs = document.querySelectorAll('select[id*="TyreSize"]').length;
    // Loop through TyreSize selects and add event listeners
    for (let i = 0; i < tyreSizeInputs; i++) { // Replace '2' with the total number of sets
        const tyreSelect = document.getElementById(`TyreSize${i}`);
        if (tyreSelect) {
            removeDuplicateOptions(`TyreSize${i}`);
            tyreSelect.addEventListener('change', () => {
                updateRimSize(`TyreSize${i}`, `ValuesTyreSize${i}`, `RimSizeIncludingOffSet${i}`, `ValuesRimSizeIncludingOffSet${i}`);
            });
        }
        // Initial update based on default TyreSize selection
        updateRimSize(`TyreSize${i}`, `ValuesTyreSize${i}`, `RimSizeIncludingOffSet${i}`, `ValuesRimSizeIncludingOffSet${i}`);
    }

    // Function to fade out the notification
    function fadeOutNotification() {
        var notification = document.getElementById('notification');
        if (notification) {
            notification.style.transition = 'opacity 1s';
            notification.style.opacity = 0;
        }
    }

    // Automatically fade out the notification after 3 seconds (adjust the time as needed)
    setTimeout(fadeOutNotification, 3000);
</script>
<?= $this->endSection() ?>