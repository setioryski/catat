function copyToClipboard(noteContentId) {
    // Get the full content from the data-full-content attribute
    var noteContent = document.getElementById(noteContentId).getAttribute('data-full-content');
    
    // Create a temporary text area element to hold the content to be copied
    var textArea = document.createElement("textarea");
    textArea.value = noteContent;  // Set the value to the full note content
    document.body.appendChild(textArea);
    
    // Select the content of the textarea
    textArea.select();
    
    // Execute the copy command
    document.execCommand('copy');
    
    // Remove the temporary text area
    document.body.removeChild(textArea);

    // Show a copy success message (optional)
    var copyMessage = document.getElementById("copyMessage");
    copyMessage.style.display = "block";
    setTimeout(function() {
        copyMessage.style.display = "none";
    }, 2000);
}


// Show Copy Message
function showCopyMessage() {
    const message = document.getElementById('copyMessage');
    message.style.display = 'block';
    setTimeout(() => {
        message.style.display = 'none';
    }, 2000);
}

// Edit Note Function
function editNote(id) {
    const titleElement = document.getElementById('title-' + id);
    const contentElement = document.getElementById('content-' + id);
    titleElement.setAttribute('contenteditable', 'true');
    contentElement.setAttribute('contenteditable', 'true');
    titleElement.classList.add('editing');
    contentElement.classList.add('editing');

    // Show full content when editing
    const fullContent = contentElement.getAttribute('data-full-content');
    if (fullContent) {
        contentElement.innerText = fullContent;
    }

    // Show Save and Cancel buttons, hide Edit button
    const noteElement = document.getElementById('note-' + id);
    noteElement.querySelector('.edit').style.display = 'none';
    noteElement.querySelector('.save').style.display = 'inline-block';
    noteElement.querySelector('.cancel').style.display = 'inline-block';
}

// Cancel Edit Function
function cancelEdit(id) {
    const titleElement = document.getElementById('title-' + id);
    const contentElement = document.getElementById('content-' + id);
    titleElement.setAttribute('contenteditable', 'false');
    contentElement.setAttribute('contenteditable', 'false');
    titleElement.classList.remove('editing');
    contentElement.classList.remove('editing');

    // Revert to original content
    const originalTitle = titleElement.getAttribute('data-original-title');
    const originalContent = contentElement.getAttribute('data-original-content');
    if (originalTitle) {
        titleElement.innerText = originalTitle;
    }
    if (originalContent) {
        contentElement.innerText = originalContent;
    }

    // Hide Save and Cancel buttons, show Edit button
    const noteElement = document.getElementById('note-' + id);
    noteElement.querySelector('.edit').style.display = 'inline-block';
    noteElement.querySelector('.save').style.display = 'none';
    noteElement.querySelector('.cancel').style.display = 'none';
}

// Save Note Function
function saveNote(id) {
    const titleElement = document.getElementById('title-' + id);
    const contentElement = document.getElementById('content-' + id);
    const title = titleElement.innerText.trim();
    const content = contentElement.innerText.trim();

    // Validate inputs
    if (title === '') {
        alert('Title cannot be empty.');
        return;
    }

    // Send POST request to save changes
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'action': 'edit',
            'id': id,
            'title': title,
            'content': content
        })
    })
    .then(response => {
        if (response.ok) {
            // Reload the page to reflect changes
            location.reload();
        } else {
            alert('Failed to save changes.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving changes.');
    });
}
