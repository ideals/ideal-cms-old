$(document).ready(function () {
    // Обходим каждый элемент с классом "images-values" так как аддонов "Фотогалерея" может быть несколько.
    $('.images-values').each(function () {
        let currentData = $(this).val();
        if (currentData !== '') {
            let currentDataArray = JSON.parse(currentData);
            let id = $(this).attr("id");
            let imageList = getImageList(currentDataArray, id);
            $('#' + id + '-list').html(imageList);
            startSortable('#' + id + '-list', '#' + id);
        }
    });

    // Вешаем событие на кнопку удаления изображения из списка
    $('.tab-pane').on('click', '.remove-image', function () {
        // Ищем поле содержащее адрес до изображения
        const imageUrl = $(this).closest('li').children('div').find('.gallery-item-url').val();

        // Ищем поле которое хранит всю информацию об изображениях в списке
        const imagesValues = $(this).closest('.tab-pane').children('.images-values');

        // Получаем всю информацию об изображениях в списке в виде текста
        const currentData = $(imagesValues).val();

        // Визуально удаляем изображение из списка
        $(this).closest('li').remove();

        // Получаем информацию об изображениях в списке в виде массива
        const currentDataArray = JSON.parse(currentData);

        // Ищем ключ верхнего уровня удаляемого элемента
        const arrayKey = secondLevelFind(currentDataArray, imageUrl);

        // Удаляем информацию об изображении из массива
        currentDataArray.splice(arrayKey, 1);

        // Записываем обновлённую информацию о списке изображений в нужное поле
        $(imagesValues).val(JSON.stringify(currentDataArray));
    });

    // При смене описания картинки пересохраняем информацию для фотогалереи
    $(".tab-pane").on('change', '.gallery-item-description', function () {
        const id = $('.images-values').attr("id");
        const listSelector = '#' + id + '-list';
        const infoSelector = '#' + id;
        rescanPhotoGalleryItems(listSelector, infoSelector);
    });
});

// Запускаем возможность сортировки списка
function startSortable(listSelector, infoSelector) {
    $(listSelector + " .sortable").sortable({
        stop: function () {
            rescanPhotoGalleryItems(listSelector, infoSelector);
        }
    });
}

// Пересобираем информацию о фотогалерее
function rescanPhotoGalleryItems(listSelector, infoSelector) {
    const urls = [];
    $(listSelector + " .sortable").find('li').each(function () {
        if ($(this).find('.gallery-item-url').val() !== undefined) {
            let url = $(this).find('.gallery-item-url').val();
            let description = $(this).find('.gallery-item-description').val();
            urls.push([url, description]);
        }
    });
    $(infoSelector).val(JSON.stringify(urls));
}


// Открывает окно CKFinder для возможности выбора изображений
function imageGalleryShowFinder(fieldSelector) {
    const finder = new CKFinder();
    finder.selectActionData = {"fieldSelector": fieldSelector};
    finder.basePath = 'js/ckfinder/';
    finder.selectActionFunction = imageGallerySetFileField;
    finder.popup();
}

// Производит работу над выбранными изображениями
function imageGallerySetFileField(fileUrl, data, allFiles) {
    const fieldSelector = '#' + data.selectActionData.fieldSelector;
    let urls = [];
    $.each(allFiles, function (index, value) {
        urls.push([value.url, '']);
    });
    let currentData = $(fieldSelector).val();
    // Если пока нет никаких данных по изображениям значит записываем только что выбранные
    if (currentData !== '') {
        let currentDataArray = JSON.parse(currentData);
        urls = currentDataArray.concat(urls);
    }
    $(fieldSelector).val(JSON.stringify(urls));
    const imageList = getImageList(urls, data.selectActionData.fieldSelector);
    $(fieldSelector + '-list').html(imageList);
    startSortable(fieldSelector + '-list', fieldSelector);
}

// Генерирует html список изображений
function getImageList(imageList, fieldId) {
    let fieldList = '';
    fieldList += '<ul id="' + fieldId + '-sortable" class="sortable">';
    $.each(imageList, function (index, value) {
        fieldList += '<li class="ui-state-default">';
        fieldList += '<div class="col-xs-1 text-center">';
        fieldList += '<span class="glyphicon glyphicon-sort" style="top: 9px; cursor: pointer;"></span>';
        fieldList += '</div>';
        fieldList += '<div class="col-xs-1 text-center">';
        fieldList += '<span class="input-group-addon" style="padding: 0 5px; width: auto;">';
        fieldList += '<img src="' + value[0] + '" style="max-height:32px; width: auto;" class="form-control gallery-item-image"';
        fieldList += ' id="gallery-item-image' + index + '">';
        fieldList += '</span>';
        fieldList += '</div>';
        fieldList += '<div class="col-xs-5 text-center">';
        fieldList += '<input type="text" class="form-control gallery-item-url" name="gallery-item-url-' + index + '"';
        fieldList += ' id="gallery-item-url' + index + '" value="' + value[0] + '">';
        fieldList += '</div>';
        fieldList += '<div class="col-xs-4 text-center">';
        fieldList += '<input type="text" class="form-control gallery-item-description"';
        fieldList += ' name="gallery-item-description' + index + '"';
        fieldList += ' id="gallery-item-description' + index + '" value="' + value[1] + '"';
        fieldList += ' placeholder="Описание изображения">';
        fieldList += '</div>';
        fieldList += '<div class="col-xs-1 text-center">';
        fieldList += '<span class="glyphicon glyphicon-remove remove-image" style="color: #FF0000; top: 7px; cursor: pointer;"></span>';
        fieldList += '</div>';
        fieldList += '</li>';
    });
    fieldList += '</ul>';
    return fieldList;
}

// Ищет элемент на втором уровне двумерного массива и возвращает ключ первого уровня
function secondLevelFind(arr, value) {
    for (let i = 0; i < arr.length; i++) {
        if (arr[i][0] === value) {
            return i;
        }
    }
}
