<?php
session_start();

include '../config/db.php';



// ==========================
// UPDATE HOTLINE
// ==========================
if (isset($_POST['update_contact'])) {


    $stmt = $pdo->prepare("
        UPDATE hotlines
        SET

            title = ?,git
            contact_number = ?,
            description = ?,
            updated_at = NOW()

        WHERE hotline_id = ?

    ");



    $stmt->execute([

        $_POST['title'],
        $_POST['contact_number'],
        $_POST['description'],
        $_POST['id']

    ]);



    header("Location: emergency-contact.php");
    exit;
}

// ==========================
// ADD HOTLINE
// ==========================
if (isset($_POST['add_hotline'])) {

    try {


        $stmt = $pdo->prepare("
            INSERT INTO hotlines
            (
                area_name,
                title,
                contact_number,
                description,
                is_emergency
            )

            VALUES
            (?, ?, ?, ?, true)
        ");



        $stmt->execute([

            // $_POST['agency'],
            // $_POST['agency'],
            $_POST['title'],
            $_POST['title'],
            $_POST['contact_number'],
            $_POST['description']

        ]);



        header("Location: emergency-contact.php");
        exit;
    } catch (PDOException $e) {

        die("ADD HOTLINE ERROR: " . $e->getMessage());
    }
}


// ==========================
// FETCH HOTLINES
// ==========================

$stmt = $pdo->query("

    SELECT

        hotline_id,
        area_name,
        title,
        contact_number,
        description

    FROM hotlines

    WHERE is_emergency = true

    ORDER BY hotline_id ASC

");


$contacts = $stmt->fetchAll();






?>



<!DOCTYPE html>

<html>


<head>


    <title>
        Emergency Contacts
    </title>



    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">



    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">



    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/add-hotline.css">
    <link rel="icon" type="image/png" href="assets/log.png">





</head>




<body>



    <?php include 'sidebar.php'; ?>




    <main class="main-content">



        <div class="dashboard-wrapper">





            <h2 class="dashboard-title">

                EMERGENCY CONTACTS

            </h2>





            <!-- ADD BUTTON -->

            <div class="d-flex justify-content-end mb-3">


                <button

                    class="btn add-hotline-btn"

                    data-bs-toggle="modal"

                    data-bs-target="#addHotlineModal">


                    <i class="bi bi-plus"></i>

                    Add Hotline


                </button>


            </div>







            <!-- ADD HOTLINE MODAL -->


            <div class="modal fade" id="addHotlineModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Create Hotline</h5>
                            <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"></button>
                        </div>

                        <form method="POST">

                            <div class="modal-body">


                                <label>AGENCY</label>

                                <input
                                    type="text"
                                    name="agency"
                                    class="form-control"
                                    placeholder="MDRRMC TALISAY"
                                    required>


                                <label class="mt-3">CONTACT NUMBER</label>

                                <input
                                    type="text"
                                    name="contact_number"
                                    class="form-control"
                                    placeholder="0939 912 6233"
                                    required>


                                <label class="mt-3">DESCRIPTION</label>

                                <textarea
                                    name="description"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Disaster Response Hotline"
                                    required></textarea>


                            </div>


                            <div class="modal-footer">

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-bs-dismiss="modal">

                                    Cancel

                                </button>


                                <button
                                    type="submit"
                                    name="add_hotline"
                                    class="btn btn-primary">

                                    Save Hotline

                                </button>


                            </div>


                        </form>

                    </div>
                </div>
            </div>









            <!-- CONTACT TABLE -->



            <div class="dash-card">





                <table class="table">



                    <thead>


                        <tr>


                            <th>

                                Agency

                            </th>


                            <th>

                                Contact Number

                            </th>


                            <th>

                                Description

                            </th>


                            <th>

                                Action

                            </th>


                        </tr>


                    </thead>





                    <tbody>

                        <?php if (count($contacts) > 0): ?>

                            <?php foreach ($contacts as $contact): ?>

                                <tr>

                                    <td>
                                        <form action="update-hotline.php" method="POST" class="d-flex gap-2 align-items-center">

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= $contact['hotline_id']; ?>">

                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                name="title"
                                                value="<?= htmlspecialchars($contact['title']); ?>">

                                    </td>

                                    <td>

                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            name="contact_number"
                                            value="<?= htmlspecialchars($contact['contact_number']); ?>">

                                    </td>

                                    <td>

                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            name="description"
                                            value="<?= htmlspecialchars($contact['description']); ?>">

                                    </td>

                                    <td class="text-nowrap">

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-sm"
                                            name="update_hotline">

                                            <i class="bi bi-save"></i>

                                        </button>

                                        <a
                                            href="delete-hotline.php?id=<?= $contact['id']; ?>"
                                            class="btn btn-danger btn-sm ms-2"
                                            onclick="return confirm('Delete this hotline?')">

                                            <i class="bi bi-trash"></i>

                                        </a>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="4" class="text-center">
                                    No emergency contacts found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>






                </table>





            </div>







        </div>


    </main>





    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>




</body>


</html>