/* The media picker behind the theme's `image` fields (currently the About photo
   on the front page). Everything else uses WordPress's own featured-image box. */
(function ($) {
    'use strict';

    $(document).on('click', '.shtob-image-field .shtob-pick', function (e) {
        e.preventDefault();
        var $field = $(this).closest('.shtob-image-field');

        var frame = wp.media({
            title: 'Выберите фотографию',
            button: { text: 'Использовать' },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            var img = frame.state().get('selection').first().toJSON();
            // medium is what the field previews at; the front end asks for its
            // own registered size, so this choice is cosmetic.
            var preview = (img.sizes && img.sizes.medium) ? img.sizes.medium.url : img.url;
            $field.find('input[type=hidden]').val(img.id);
            $field.find('img').attr('src', preview).show();
            $field.find('.shtob-clear').show();
        });

        frame.open();
    });

    $(document).on('click', '.shtob-image-field .shtob-clear', function (e) {
        e.preventDefault();
        var $field = $(this).closest('.shtob-image-field');
        $field.find('input[type=hidden]').val('');
        $field.find('img').hide();
        $(this).hide();
    });
})(jQuery);
