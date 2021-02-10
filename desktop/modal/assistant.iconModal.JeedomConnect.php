<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

?>
<style>
  .row:after {
  content: "";
  display: table;
  clear: both;
}

.column {
  float: left;
  width: 25%;
  padding: 10px;
}

/* Style the images inside the grid */
.column img {
  opacity: 0.8;
  cursor: pointer;
}

.selected {
	border: solid;
	border-color: red;
}
</style>
<div style="overflow-y:auto; overflow-x:hidden; ">
<input type="file" accept="image/*" id="file-input" style="display:none;" >

<div class="panel-group" id="accordionIcon">
  <div class="panel panel-default">
	<div class="panel-heading">
	<h3 class="panel-title">
	   <a class="accordion-toggle" data-toggle="collapse" data-parent="" aria-expanded="true" href="">Icônes Jeedom</a>
	</h3>
	</div>
	<div id="jeeCon" class="panel-collapse collapse">
	   <div class="panel-body">
	      <div class="scenarioListContainer">


          <div role="tabpanel" class="tab-pane active" id="tabicon" style="width:calc(100% - 20px)">
          		<?php
          		$scanPaths = array('core/css/icon', 'data/fonts');
          		$div = '';
          		foreach ($scanPaths as $root) {
          			$ls = ls($root, '*');
          			foreach ($ls as $dir) {
          				$root .= '/';
          				if (!is_dir($root . $dir) || !file_exists($root . $dir . '/style.css')) {
          					continue;
          				}
          				$fontfile = $root . $dir . 'fonts/' . substr($dir, 0, -1) . '.ttf';
          				if (!file_exists($fontfile)) continue;

          				$css = file_get_contents($root . $dir . '/style.css');
          				$research = strtolower(str_replace('/', '', $dir));
          				preg_match_all("/\." . $research . "-(.*?):/", $css, $matches, PREG_SET_ORDER);
          				$div .= '<div class="iconCategory"><legend>' . str_replace('/', '', $dir) . '</legend>';

          				$number = 1;
          				foreach ($matches as $match) {
          					if (isset($match[0])) {
          						if ($number == 1) {
          							$div .= '<div class="row">';
          						}
          						$div .= '<div class="col-lg-1 divIconSel">';
          						$icon = str_replace(array(':', '.'), '', $match[0]);
          						$div .= '<span class="iconSel"><i class=\'icon ' . $icon . '\'></i></span><br/><span class="iconDesc">' . $icon . '</span>';
          						$div .= '</div>';
          						$number++;
          					}
          				}
          				if($number != 0){
          					$div .= '</div>';
          				}
          				$div .= '</div>';
          			}
          		}
          		echo $div;
          		?>


	</div>
	</div>
	</div>
	</div>
</div>




<br/>
Images internes
<div class="row" id="internal-div"></div>
Images personnelles
<a class="btn btn-default roundedRight" onclick="addImage()">
	<i class="fas fa-plus-square"></i>
	Ajouter
</a>

<div class="row" id="user-div">

</div>
</div>
<script>

function addImage() {
	$("#file-input").click();
}

$("#file-input").change(function() {
	var fd = new FormData();
	if($(this).prop('files').length > 0)
    {
        file =$(this).prop('files')[0];
        fd.append("file", file);
		fd.append("action", "uploadImg");
		console.log(fd)
		$.post({
            url: 'plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php',
            data: fd,
            contentType: false,
            processData: false,
            success: function(response){
				setImageModalData();
            },
        });

    }
});

function selectImage(img) {
	$(".selected").removeClass("selected");
	$(img).addClass("selected");
	$( "#validateImg").click();
}
function setImageModalData(selected) {
	$.post({
        url: 'plugins/JeedomConnect/core/ajax/jeedomConnect.ajax.php',
		data: { 'action': 'getImgList' },
		cache: false,
        success: function(response){
            files = $.parseJSON(response).result;
			internalContent = "";
			for (var key in files.internal) {
				if (files.internal[key] == selected) {
					internalContent += `<div class="column">
					<img src="plugins/JeedomConnect/data/img/${files.internal[key]}" id="${files.internal[key]}" style="width:40px" class="selected" onclick="selectImage(this);">
					</div>`;
				} else {
					internalContent += `<div class="column">
					<img src="plugins/JeedomConnect/data/img/${files.internal[key]}" id="${files.internal[key]}" style="width:40px" onclick="selectImage(this);">
					</div>`;
				}

			}
			$("#internal-div").html(internalContent);
			userContent = "";
			for (var key in files.user) {
				if (files.user[key] == selected) {
					userContent += `<div class="column">
					<img src="plugins/JeedomConnect/data/img/user_files/${files.user[key]}" id="user_files/${files.user[key]}" class="selected" style="width:40px" onclick="selectImage(this);">
					</div>`;
				} else {
					userContent += `<div class="column">
					<img src="plugins/JeedomConnect/data/img/user_files/${files.user[key]}" id="user_files/${files.user[key]}"  style="width:40px" onclick="selectImage(this);">
					</div>`;
				}
			}
			$("#user-div").html(userContent);
        },
    });
}




</script>
