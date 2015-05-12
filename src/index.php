<?php
// Turn on all error reporting.
error_reporting (E_ALL);
ini_set ('display_errors', 'On');
include 'storedInfo.php';

$db = new mysqli ("oniddb.cws.oregonstate.edu", "lora-db", $myPassword, "lora-db");
if ($db->connect_errno) {
	echo "ERROR: Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
}

//Checks to see if there is a duplicate named video
function dupName($vidName, $db) {
	if (! ($nameList = $db->query ( "SELECT name FROM movies WHERE name=\"{$vidName}\"" ))) {
		echo "Name query failed: (" . $db->errno . ") " . $db->error;
	}
	return mysqli_num_rows($nameList);
}

//Delete a video
function deleteVid($vidId, $db) {
	if (! ($db->query ( "DELETE FROM movies WHERE id={$vidId}" ))) {
		echo "Delete failed: (" . $db->errno . ") " . $db->error;
	}
}

//Movie check in/check out
function status($vidId, $db) {
	if (! ($db->query ( "UPDATE movies SET rented = !rented WHERE id={$vidId}" ))) {
		echo "Update failed: (" . $db->errno . ") " . $db->error;
	}
}

//Clear table
function truncateTable($db) {
	if (! ($db->query ( "TRUNCATE TABLE movies" ))) {
		echo "Truncation failed: (" . $db->errno . ") " . $db->error;
	}
}

if ($_POST) {
	if(isset($_POST['status'])) {
		$vidId = $_POST['status'];	
		status($vidId, $db);
	}

	if(isset($_POST['delete'])) {
		$vidId = $_POST ['delete'];
		deleteVid($vidId, $db);
	}
	
	if(isset($_POST['delAll'])) {
		truncateTable($db);
	}
	
	$validated = TRUE;

	if (isset ( $_POST ['name'] ) && ($_POST ['name'] != NULL)) {
		$inName = $_POST ['name'];
		if(dupName($inName, $db) == 0) {
           // sets category, sets to "Uncategorized" if none specified
           if ((isset ( $_POST ['category'] )) && ($_POST ['category'] != NULL)) {
                   $inCat = $_POST ['category'];
           } else {
                   $inCat = "[Uncategorized]";
           }
           
           if ((isset ( $_POST ['minutes'] ) && ($_POST ['minutes'] != NULL))) {
                   if (( string ) ( int ) $_POST ['minutes'] === ( string ) $_POST ['minutes']) {
                           // Check that the number of minutes is is >= 0
                           if (( int ) $_POST ['minutes'] >= 0) {
                                   $inLength = $_POST ['minutes'];
                           } else {
                                   echo "<p>ERROR: Video length must be a positive number.</p>";
                                   $validated = FALSE;
                           }
                   } else {
                           echo "<p>ERROR: Video length must be an integer.</p>";
                           $validated = FALSE;
                   }
           			} else {

                   			$inLength = NULL;
          		 }
           
           if ($validated === TRUE) {
                   if (! ($stmt = $db->prepare ( "INSERT INTO movies(name,category,length) VALUES (?,?,?)" ))) {
                           echo "ERROR: Prepare failed: (" . $db->errno . ") " . $db->error;
                   }
                   if (! $stmt->bind_param ( "ssi", $inName, $inCat, $inLength )) {
                           echo "ERROR: Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
                   }
                   if (! $stmt->execute ()) {
                           echo "ERROR: Execute failed: (" . $stmt->errno . ") " . $stmt->error;
                   }
                   $stmt->close ();
           }
		} else {
			echo "<p>ERROR: Video name exists already, name must be changed.</p>\n";
		}
	} elseif ((isset ( $_POST ['category'] ) || (isset ( $_POST ['minutes'] )))) {
		echo "<p>ERROR: The name field is required when adding videos.</p>\n";
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Assignment 4 Part 2</title>
</head>
<body>

	<form method="POST" action="index.php">
		<fieldset>
			<legend>Add Video</legend>
			<p>
				<label>Name (required):</label>
				<input type="text" name="name">
				<label>Category:</label>
				<input type="text" name="category">
				<label>Length (minutes): <input type="number" name="minutes"></label>
				<input type="submit" value="Add Video">
		</fieldset>
	</form>
	<p>
		<h2>Video Inventory</h2>
		<legend>Filter by Category:</legend>
		<form method="POST" action="index.php">
			<select name="showCategory">
		</form>

<?php

if (! ($catList = $db->prepare ( "SELECT DISTINCT category FROM movies ORDER BY category" ))) {
	echo "Prepare failed: (" . $db->errno . ") " . $db->error;
}
$inCat = NULL;
if (! $catList->bind_result ( $inCat )) {
	echo "Binding output parameters failed: (" . $catList->errno . ") " . $catList->error;
}
if (! $catList->execute ()) {
	echo "Execute failed: (" . $catList->errno . ") " . $catList->error;
}

$catList->store_result ();
if ($catList->num_rows () > 0) {
	echo "\t\t\t<option selected value=\"allMovies\">All Movies</option>\n";
	while ( $catList->fetch () ) {
		echo "\t\t\t<option value=\"{$inCat}\">{$inCat}</option>\n";
	}
}
$catList->close ();
?>
		</select> <input type="submit" value="Filter">
	</form>
<p>
	
<?php
$queryStr = "SELECT id, name, category, length, rented FROM movies";
if (isset ( $_POST ['showCategory'] ) && ($_POST ['showCategory'] !== "allMovies")) {
	$queryStr .= " WHERE category=\"" . $_POST ['showCategory'] . "\"";
}
if (! ($stmt = $db->prepare ( $queryStr ))) {
	echo "Prepare failed: (" . $db->errno . ") " . $db->error;
}
if (! $stmt->execute ()) {
	echo "Execute failed: (" . $db->errno . ") " . $db->error;
}

$outId = NULL;
$outName = NULL;
$outCat = NULL;
$outLength = NULL;
$outStatus = NULL;
if (! $stmt->bind_result ( $outId, $outName, $outCat, $outLength, $outStatus )) {
	echo "Binding output parameters failed: (" . $stmt->errno . ") " . $stmt->error;
}
?>
	<form action="index.php" method="post" name="vidTableForm">
		<table border="1">
			<tbody>
				<tr>
					<th>Name</th>
					<th>Category</th>
					<th>Length</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
<?php
// Populate the table rows with movie data.
while ( $stmt->fetch () ) {
	$outStatusTxt = ($outStatus === 0 ? 'Available' : 'Checked out');
	printf ( "<tr>\n" . "\t<td>%s</td>\n" . "\t<td>%s</td>\n" . "\t<td>%d</td>\n" . "\t<td>%s</td>\n" . "\t<td><button type=\"submit\" name=\"status\"" . " value=\"{$outId}\">Check in/out</button>\n" . "<button type=\"submit\" name=\"delete\"" . " value=\"{$outId}\">Delete</button></td>\n" . "</tr>\n", $outName, $outCat, $outLength, $outStatusTxt );
}
$stmt->close ();
?>
		</tbody>
		</table>
	</form>
	<p>
	<form method="POST" action="index.php" name="deleteAll">
		<input type="submit" name="delAll" value="Delete all videos">
	</form>
</body>
</html>