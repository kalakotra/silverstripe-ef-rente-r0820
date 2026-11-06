
    <div class="row">
        <div class="col-12">
            <h2><%t PDFMultiFormPage\\Form_Preview.PageHeader 'Vorschau' %></h2>
            <p><%t PDFMultiFormPage\\Form_Preview.Intro 'Hier können Sie Ihre Angaben überprüfen, bevor Sie das Formular absenden.' %></p>
            <% loop $PreviewSteps %>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">$Title</h5>
                    </div>
                    <div class="card-body">
                        <% loop $Fields %>
                            <div class="mb-3">
                                <strong>$Label</strong>
                                <br />
                                $Value
                            </div>
                        <% end_loop %>
                    </div>
                </div>
            <% end_loop %>
        </div>
    </div>

<hr />
<form $AttributesHTML>
    <% if $Message %>
    <p id="{$FormName}_error" class="message $MessageType">$Message</p>
    <% else %>
    <p id="{$FormName}_error" class="message $MessageType" style="display: none"></p>
    <% end_if %>
    <fieldset id="{$FormName}_Fields" class="Fields">
        $Fields
    </fieldset>
    <% if $Actions %>
        <div class="btn-toolbar">
            <% loop $Actions %>
                $Field
            <% end_loop %>
        </div>
    <% end_if %>
</form>