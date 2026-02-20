// Ensures that at least one of the searchable fields are enabled: if all three are disabled the entire display
// type becomes pointless as all searches will result in 'no images found'
(function() {
    // Find all of our search yes/no combinations
    const group = [
        document.querySelectorAll('input.imagely-search_search_alttext'),
        document.querySelectorAll('input.imagely-search_search_description'),
        document.querySelectorAll('input.imagely-search_search_tags')
    ];

    // Merge them into one big array for convenience
    // Using var here with const is weird but is necessary for GulpUglify to not halt the entire package build process
    var input_fields = [];
    group.forEach(function(subgroup) {
        subgroup.forEach(function (child) {
            input_fields = input_fields.concat(child);
        });
    });

    // Determines if any of our search fields are enabled
    const are_any_enabled = function() {
        var enabled = false;
        input_fields.forEach(function(child) {
            if (child.checked === true && parseInt(child.value) === 1) {
                enabled = true;
            }
        });
        return enabled;
    };

    // The event listener: one of the search fields MUST be enabled
    input_fields.forEach(function(child) {
        child.addEventListener('change', function(event) {
            const enabled = are_any_enabled();
            if (!enabled) {
                setTimeout(function() {
                    document.getElementById('imagely-search_search_tags').checked = true;
                }, 200);
            }
        });
    });
})();