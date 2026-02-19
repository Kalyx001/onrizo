function confirmDelete(id) {
    if (confirm("⚠️ Are you sure you want to delete this product? This action cannot be undone.")) {
        // Use fetch for smooth delete
        fetch(`delete_product.php?id=${id}`, { credentials: 'same-origin' })
            .then(res => res.text())
            .then(data => {
                alert(data.trim());
                location.reload();
            })
            .catch(err => {
                alert("❌ Error deleting product.");
                console.error(err);
            });
    }
}
