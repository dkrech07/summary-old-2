var addNewItemElement = document.querySelector('.add-new-item');
var settingsElement = document.querySelector('.settings-item');
var summaryTableElement = document.querySelector('.summary-table');

// Подстветка статусов на элементах списка
var summaryElement = summaryTableElement.querySelectorAll('.summary-item');
summaryElement.forEach(element => {
  statusElement = element.querySelector('.status');
  if (statusElement.dataset.status == 3) {
    statusElement.style.color = 'blue';
  }
  if (statusElement.dataset.status == 2) {
    statusElement.style.color = 'green';
  }
  if (statusElement.dataset.status == 1) {
    statusElement.style.color = 'red';
  }
});

// Создание записи

addNewItemElement.addEventListener('click', evt => {
  evt.preventDefault();
  $('#NewItemModal').modal('show');

  var newItemModalElement = document.querySelector('#NewItemModal');
  var newAudioElement = newItemModalElement.querySelector('.new-audio');
  var newDetailElement = newItemModalElement.querySelector('.new-detail');

  newAudioElement.addEventListener('click', evt => {
    $('#NewItemModal').modal('hide');
    $('#audioModal').modal('show');
    var newAudioForm = document.querySelector('#audio');
    // newAudioForm.reset();
  });

  newDetailElement.addEventListener('click', evt => {
    $('#NewItemModal').modal('hide');
    $('#detailModal').modal('show');
    var newDetailForm = document.querySelector('#detail');
    newDetailForm.reset();
  });
});

// Редактирование подробного и краткого описания
var itemEditElement = summaryTableElement.querySelectorAll('.item-edit');

var detailEditElement = summaryTableElement.querySelectorAll('.detail-edit');
var summaryEditElement = summaryTableElement.querySelectorAll('.summary-edit');

itemEditElement.forEach(element => {
  element.addEventListener('click', evt => {
    var editParam = element.classList[1];

    if (editParam === 'detail') {
      var data = {
        'item_id_detail': element.parentNode.id
      };

      $.ajax({
        url: '/summary/web/site/edit',
        type: 'POST',
        data: data,
        success: function (response) {
          var itemData = JSON.parse(response);
          // var itemData = JSON.stringify(response);
          // console.log(data);
          // console.log(itemData);
          var itemModalElement = document.querySelector('#' + editParam + 'Modal');

          var tabs = itemModalElement.querySelector('.tabs');

          var tabsElements = tabs.querySelectorAll('.btn');
          if (tabsElements) {
            tabsElements.forEach(element => {
              tabs.removeChild(element);
            });
          }

          itemData.forEach((element, index, array) => {
            var tabElement = document.createElement("button");
            if (index < 1) {
              tabElement.className = "btn btn-primary";
            } else {
              tabElement.className = "btn btn-secondary";
            }
            tabElement.type = 'button';
            tabElement.textContent = 'Вариант ' + (index + 1);
            tabElement.id = index;
            tabs.appendChild(tabElement);

            var itemModalElementTitle = itemModalElement.querySelector('#itemform-' + 'title');
            var itemModalElementInput = itemModalElement.querySelector('#itemform-' + editParam);
            itemModalElementTitle.value = itemData[0]['title'];
            itemModalElementInput.value = itemData[0][editParam + '_text'];

            tabElement.addEventListener('click', evt => {
              tabsElements.forEach(item => {
                if (item.classList.contains('btn-primary')) {
                  item.classList.remove('btn-primary');
                }
                item.classList.add('btn-secondary');
              });

              itemModalElementTitle.value = itemData[index]['title'];
              itemModalElementInput.value = itemData[index][editParam + '_text'];

              tabElement.classList.remove('btn-secondary');
              tabElement.classList.add('btn-primary');
            });
          });

          var tabsElements = tabs.querySelectorAll('.btn');

          if (itemData) {
            $('#' + editParam + 'Modal').modal('show');
          }
        }
      });

    } else if (editParam === 'summary') {
      var data = {
        'item_id_summary': element.parentNode.id
      };

      console.log(editParam);

      $.ajax({
        url: '/summary/web/site/edit',
        type: 'POST',
        data: data,
        success: function (response) {
          var itemData = JSON.parse(response);
          var itemModalElement = document.querySelector('#' + editParam + 'Modal');

          var itemModalElementTitle = itemModalElement.querySelector('#itemform-' + 'title');
          var itemModalElementInput = itemModalElement.querySelector('#itemform-' + editParam);
          itemModalElementTitle.value = itemData['title'];
          itemModalElementInput.value = itemData[editParam + '_text'];

          console.log(data);
          console.log(itemData);

          // var tabs = itemModalElement.querySelector('.tabs');

          // var tabsElements = tabs.querySelectorAll('.btn');
          // if (tabsElements) {
          //   tabsElements.forEach(element => {
          //     tabs.removeChild(element);
          //   });
          // }

          // itemData.forEach((element, index, array) => {
          //   var tabElement = document.createElement("button");
          //   if (index < 1) {
          //     tabElement.className = "btn btn-primary";
          //   } else {
          //     tabElement.className = "btn btn-secondary";
          //   }
          //   tabElement.type = 'button';
          //   tabElement.textContent = 'Вариант ' + (index + 1);
          //   tabElement.id = index;
          //   tabs.appendChild(tabElement);

          //   var itemModalElementTitle = itemModalElement.querySelector('#itemform-' + 'title');
          //   var itemModalElementInput = itemModalElement.querySelector('#itemform-' + editParam);
          //   itemModalElementTitle.value = itemData[0]['title'];
          //   itemModalElementInput.value = itemData[0][editParam + '_text'];

          //   tabElement.addEventListener('click', evt => {
          //     tabsElements.forEach(item => {
          //       if (item.classList.contains('btn-primary')) {
          //         item.classList.remove('btn-primary');
          //       }
          //       item.classList.add('btn-secondary');
          //     });

          //     itemModalElementTitle.value = itemData[index]['title'];
          //     itemModalElementInput.value = itemData[index][editParam + '_text'];

          //     tabElement.classList.remove('btn-secondary');
          //     tabElement.classList.add('btn-primary');
          //   });
          // });

          // var tabsElements = tabs.querySelectorAll('.btn');

          if (itemData) {
            $('#' + editParam + 'Modal').modal('show');
          }
        }
      });
    }

  });
});

settingsElement.addEventListener('click', evt => {
  evt.preventDefault();
  $('#accountModal').modal('show');
});

// $('#audioModal').modal('show');

$(document).on('pjax:beforeSend', function () {
  // $('.appeal-list').hide();
  $('#loader').show();
})

// summaryEditElement.forEach(element => {
//   element.addEventListener('click', evt => {


//     $.ajax({
//       url: '/site/index',
//       type: 'POST',
//       data: data,
//       success: function (response) {
//         var summaryData = JSON.parse(response);
//         var itemModalElement = document.querySelector('#itemModal');
//         var summaryModalInput = itemModalElement.querySelector('#itemform-summary');
//         summaryModalInput.value = summaryData['summary'];
//         if (summaryData) {
//           $('#itemModal').modal('show');
//         }
//       }
//     });
//   });
// });

// var openModal = (element) => {
//   if (element.className === 'detail') {
//     $('#summaryModal.modal-title').textContent = 'Подробное описание';
//   }
//   if (element.className === 'summary') {
//   }
//   $('#summaryModal').modal('show');
//   // var modalElement = document.querySelector('#summaryModal');
//   // modalElement.show();
// };



// // получим кнопку id="btn" с помощью которой будем открывать модальное окно
// const btn = document.querySelector('#btn');
// // активируем контент id="modal" как модальное окно
// const modal = new bootstrap.Modal(document.querySelector('#modal'));
// // при нажатии на кнопку
// btn.addEventListener('click', function () {
//   // открываем модальное окно
//   modal.show();
// });

// var exampleModal = document.getElementById('exampleModal');
// console.log(exampleModal);
// exampleModal.addEventListener('show.bs.modal', function (event) {
//   console.log(exampleModal);
//   // Кнопка, запускающая модальное окно
//   var button = event.relatedTarget
//   // Извлечь информацию из атрибутов data-bs- *
//   var recipient = button.getAttribute('data-bs-whatever')
//   // При необходимости вы можете инициировать запрос AJAX здесь
//   // а затем выполните обновление в обратном вызове.
//   //
//   // Обновите содержимое модального окна.
//   var modalTitle = exampleModal.querySelector('.modal-title')
//   var modalBodyInput = exampleModal.querySelector('.modal-body input')

//   modalTitle.textContent = 'Новое сообщение для ' + recipient
//   modalBodyInput.value = recipient
// });