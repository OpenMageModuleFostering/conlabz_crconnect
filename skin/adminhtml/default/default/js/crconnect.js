Crconnect = Class.create();
Crconnect.prototype = {
    initialize: function (options, elements) {
        this.options = options;
        this.elements = elements;
    },
    confirmMainKey: function (manual) 
    {
        var key = $("crroot_crconnect_api_key").value,
            self = this;
        if (manual) {
            $$(".simple-added-row").each(function (el) {
                callRowDelete(el.id);
                self.options.savedDefaultListId = false;
                self.options.savedDefaultFormId = false;
            });
        }

        if (key) {
            var url = this.options.baseConfirmKeyUrl + 'crconnectkey/' + key;
            new Ajax.Request(url, {
                method: 'get',
                onSuccess: function (transport) {
                    var select = $('crroot_crconnect_list_id');
                    self.cleanSelect('crroot_crconnect_list_id');
                    // If no results come, revert all group and form selects to default states
                    if (transport.responseText == "empty") {
                        self.resetGroupList();
                        self.resetFormList();
                        self.reloadKeysBlock();
                        self.confirmDisable();
                        return false;
                    }
                    // Fill Main groups select with groups list
                    var editData = transport.responseText.evalJSON(true);
                    self.reloadKeysBlock(editData);
                    select.insert(new Element('option', {
                        value: ''
                    }).update(Translator.translate('Please select subscribers group')));
                    for (var i = 0; i < editData.length; i++) {
                        select.insert(new Element('option', {
                            value: editData[i].id
                        }).update(editData[i].name));
                    }

                    if (self.options.savedDefaultListId) {
                        select.value = self.options.savedDefaultListId;
                        self.options.savedDefaultListId = false;
                    }
                    self.changeGroupId();
                }
            });
        } else {
            this.resetGroupList();
        }
        this.confirmDisable();
    },
    reloadKeysBlock: function (editData) 
    {
        var select = '<select class="crconnect-groups-select" onchange="CrC.changeSubGroup(this)" id="#{_id}_crconnect" name="groups[crconnect][fields][groups_keys][value][#{_id}][crconnect]">';
        select += '<option value="">' + Translator.translate('Please select subscribers group') + '</option>';
        if (editData) {
            for (var i = 0; i < editData.length; i++) {
                select += '<option value="' + editData[i].id + '">' + editData[i].name + '</option>';
            }
        }
        this.elements.stringElements['crconnect'] = select + '</select>';
        initRowTemplate();
    },
    changeGroupId: function () 
    {
        var groupId = $("crroot_crconnect_list_id").value;
        var key = $("crroot_crconnect_api_key").value;
        var self = this;
        if (key && groupId) {
            var url = self.options.baseChangeGroupUrl + 'group/' + groupId + '/crconnectkey/' + key;
            new Ajax.Request(url, {
                method: 'get',
                onSuccess: function (transport) {
                    var select = $('crroot_crconnect_form_id');
                    self.cleanSelect('crroot_crconnect_form_id');
                    if (transport.responseText == "empty") {
                        self.resetFormList();
                        return false;
                    }
                    var editData = transport.responseText.evalJSON(true);
                    select.insert(new Element('option', {
                        value: ''
                    }).update(Translator.translate('Please select form')));
                    for (var i = 0; i < editData.length; i++) {
                        select.insert(new Element('option', {
                            value: editData[i].id
                        }).update(editData[i].name));
                    }

                    if (self.options.savedDefaultFormId) {
                        select.value = self.options.savedDefaultFormId;
                        self.options.savedDefaultFormId = false;
                    }
                }
            });
        } else {
            this.resetFormList();
        }
    },
    changeSubGroup: function (element) 
    {
        var selectedValue = element.value,
            id = element.id.replace("_crconnect", ""),
            key = $("crroot_crconnect_api_key").value,
            url = this.options.baseChangeGroupUrl + 'group/' + selectedValue + '/crconnectkey/' + key,
            self = this;
        
        new Ajax.Request(url, {
            method: 'get',
            onSuccess: function (transport) {
                var select = $(id + '_formid');
                self.cleanSelect(id + '_formid');
                if (transport.responseText == "empty") {
                    self.resetFormList(id + '_formid');
                    return false;
                }
                var editData = transport.responseText.evalJSON(true);
                select.insert(new Element('option', {
                    value: ''
                }).update(Translator.translate('Please select form')));
                for (var i = 0; i < editData.length; i++) {
                    select.insert(new Element('option', {
                        value: editData[i].id
                    }).update(editData[i].name));
                }

                if (self.options.savedFormsKeys) {
                    if (self.options.savedFormsKeys[id]) {
                        select.value = self.options.savedFormsKeys[id];
                    }
                }
            }
        });
    },
    confirmEnable: function () 
    {
        $("confirm-key-button").removeClassName('disabled');
        $("confirm-key-button").disabled = false;
    },
    confirmDisable: function () 
    {
        $("confirm-key-button").addClassName('disabled');
        $("confirm-key-button").disabled = true;
    },
    resetFormList: function (formId) 
    {
        if (!formId) {
            formId = 'crroot_crconnect_form_id';
        }
        this.cleanSelect(formId);
        $(formId).insert(new Element('option', {
            value: ''
        }).update(Translator.translate('No forms to select')));
    },
    resetGroupList: function (formId) 
    {
        if (!formId) {
            formId = 'crroot_crconnect_list_id';
        }
        this.cleanSelect(formId);
        $(formId).insert(new Element('option', {
            value: ''
        }).update(Translator.translate('No groups to select')));
    },
    cleanSelect: function (selectId) 
    {
        var options = $$('select#' + selectId + ' option');
        for (var i = 0; i < options.length; i++) {
            options[i].remove();
        }
    },
    fillSelectedGroups: function () 
    {
        if (typeof this.elements.editedSelects != "undefined") {
            if (this.elements.editedSelects.length > 0) {
                for (var i = 0; i < this.elements.editedSelects.length; i++) {
                    this.changeSubGroup($(this.elements.editedSelects[i] + "_crconnect"));
                }
            }
        }
    }
};

var CrC;
function initCleverReach() 
{
    try {
        CrC = new Crconnect(crconnectOptions, crconnectElements);
        CrC.confirmMainKey();
        Event.observe('crroot_crconnect_api_key', 'keyup', function () { CrC.confirmEnable(); });
        Event.observe('crroot_crconnect_list_id', 'change', function () { CrC.changeGroupId(); });
        CrC.fillSelectedGroups();
    } catch (e) {
        console.log(e);
    }
}
Event.observe(window, 'load', initCleverReach);
