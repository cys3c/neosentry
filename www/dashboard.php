<?php //dashboard.php - DASHBOARD
/*  Loaded via AJAX so no html/header/body tags should be present.
 * 
 *  For good template examples check out:
 *      http://wrapbootstrap.com/preview/WB0573SK0
 *      http://demo.flatlogic.com/3.3.1/dark/index.html
 */
?>

<h2 class="page-title">Validation <small>Form validation</small></h2>
<div class="row">
    <div class="col-md-7">
        <section class="widget">
            <header>
                <h4>
                    <i class="fa fa-check-square-o"></i>
                    Dead simple validation
                    <small>No JS needed to tune-up</small>
                </h4>
            </header>
            <div class="body">
                <form id="validation-form" class="form-horizontal form-label-left" method="post"
                      data-parsley-priority-enabled="false"
                      novalidate="novalidate">
                    <fieldset>
                        <legend class="section">
                            By default validation is started only after at least 3 characters have been input.
                        </legend>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="basic">Simple required</label>
                            <div class="col-md-5">
                                <input type="text" id="basic" name="basic" class="form-control input-transparent"
                                       required="required">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="basic-change">
                                Min-length On Change
                            <span class="help-block">
                                At least 10
                            </span>
                            </label>
                            <div class="col-md-5">
                                <input type="text" id="basic-change" name="basic-change" class="form-control input-transparent"
                                       data-parsley-trigger="change"
                                       data-parsley-minlength="10"
                                       required="required">
                            </div>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="section">
                        <span class="label label-important">
                            HTML5
                        </span>
                            input types supported
                        </legend>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="email">
                                E-mail
                            </label>
                            <div class="col-md-5">
                                <input type="email" id="email" name="email" class="form-control input-transparent"
                                       data-parsley-trigger="change"
                                       data-parsley-validation-threshold="1"
                                       required="required">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="number">
                                Number
                            </label>
                            <div class="col-md-5">
                                <input type="text" id="number" name="number" class="form-control input-transparent"
                                       data-parsley-type="number"
                                       required="required">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="range">
                                Range
                            </label>
                            <div class="col-md-5">
                                <input type="text"  class="form-control input-transparent"
                                       id="range" name="range"
                                       data-parsley-range="[10, 100]"
                                       data-parsley-trigger="change"
                                       data-parsley-validation-threshold="1"
                                       required="required">
                            </div>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="section">
                            More validation
                        </legend>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="password">
                                Password helpers
                            </label>
                            <div class="col-md-5">
                                <input type="password" id="password" name="password" class="form-control input-transparent"
                                       data-parsley-trigger="change"
                                       data-parsley-minlength="6"
                                       required="required">
                                <input type="password" id="password-r" name="password-r" class="form-control input-transparent mt-sm"
                                       data-parsley-trigger="change"
                                       data-parsley-minlength="6"
                                       data-parsley-equalto="#password"
                                       required="required">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label col-md-4" for="website">
                                Website
                            </label>
                            <div class="col-md-5">
                                <input type="text" id="website" name="website" class="form-control input-transparent"
                                       data-parsley-trigger="change"
                                       data-parsley-type="url"
                                       required="required">
                            </div>
                        </div>
                    </fieldset>
                    <div class="form-actions">
                        <div class="row">
                            <div class="col-md-8 col-md-offset-4">
                                <button type="submit" class="btn btn-danger">Validate &amp; Submit</button>
                                <button type="button" class="btn btn-default">Cancel</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<!-- Add page specific javascript here -->
<script src="plugins/parsleyjs/parsley.min.js"></script>   <!-- form validation -->
<script> 
    //$(function(){function a(){$("#validation-form").parsley(),$(".widget").widgster()}a(),PjaxApp.onPageLoad(a)});
</script>  

<!-- END PAGE CONTENT -->
