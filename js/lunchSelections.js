 // manage check boxes for the lunch section
 function handleSelectAll() {
    const selectAll = document.getElementById("lunch_select-all");
    const checkboxes = document.querySelectorAll(".lunch-choice-checkbox");

    if (selectAll.checked) {
        checkboxes.forEach(checkbox => checkbox.checked = true);
    } else {
        checkboxes.forEach(checkbox => checkbox.checked = false);
    }
}

function handleCheckboxChange() {
    const selectAll = document.getElementById("lunch_select-all");
    const checkboxes = document.querySelectorAll(".lunch-choice-checkbox");
    
    for(var i=1; i<6; i++){
        if(checkboxes[i].checked == false) {
            selectAll.checked = false;
        }
    }
    
}