(function() {
    var forms = document.getElementsByClassName('ngg-image-search-form');
    for (var i = 0; i < forms.length; i++) {
        forms[i].addEventListener('submit', function(event) {
            event.preventDefault();
            window.location = this.dataset.submissionUrl.replace(
                'ngg-search-placeholder',
                encodeURIComponent(this.getElementsByClassName('ngg-image-search-input')[0].value)
            );
        });
    }
})();