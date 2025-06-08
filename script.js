document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const pollForm = document.getElementById('poll-form');
    const addOptionBtn = document.getElementById('add-option');
    const showRegister = document.getElementById('show-register');
    const authSection = document.getElementById('auth-section');
    const pollSection = document.getElementById('poll-section');
    let userId = null;

    showRegister.addEventListener('click', () => {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('reg-username').value;
        const password = document.getElementById('reg-password').value;
        const response = await fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=register&username=${username}&password=${password}`
        });
        const result = await response.json();
        alert(result.message);
        if (result.success) {
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
        }
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const response = await fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&username=${username}&password=${password}`
        });
        const result = await response.json();
        if (result.success) {
            userId = result.user_id;
            authSection.style.display = 'none';
            pollSection.style.display = 'block';
            loadPolls();
        } else {
            alert(result.message);
        }
    });

    addOptionBtn.addEventListener('click', () => {
        const optionInput = document.createElement('input');
        optionInput.type = 'text';
        optionInput.className = 'option';
        optionInput.placeholder = `Option ${document.querySelectorAll('.option').length + 1}`;
        optionInput.required = true;
        document.getElementById('options').appendChild(optionInput);
    });

    pollForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = document.getElementById('poll-question').value;
        const expiry = document.getElementById('poll-expiry').value;
        const options = Array.from(document.querySelectorAll('.option')).map(opt => opt.value);
        const response = await fetch('poll.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', question, options, expiry, user_id: userId })
        });
        const result = await response.json();
        alert(result.message);
        if (result.success) loadPolls();
    });

    async function loadPolls() {
        const response = await fetch('poll.php?action=list');
        const polls = await response.json();
        const pollsList = document.getElementById('polls-list');
        pollsList.innerHTML = '';
        polls.forEach(poll => {
            const pollDiv = document.createElement('div');
            pollDiv.className = 'poll';

            const userVoted = poll.voters && poll.voters.includes(userId);
            const isExpired = new Date(poll.expiry) < new Date();

            pollDiv.innerHTML = `
                <h3>${poll.question}</h3>
                <p>Expires: ${new Date(poll.expiry).toLocaleString()}</p>
                ${userVoted ? '<p><strong>You already voted</strong></p>' : ''}
                <form class="vote-form" data-poll-id="${poll.id}">
                    ${poll.options.map(opt => `
                        <label><input type="radio" name="option" value="${opt.id}">${opt.text}</label>
                    `).join('')}
                    <button type="submit" ${isExpired || userVoted ? 'disabled' : ''}>Vote</button>
                </form>
                <canvas id="chart-${poll.id}"></canvas>
            `;

            pollsList.appendChild(pollDiv);
            renderChart(poll);

            pollDiv.querySelector('.vote-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const optionId = e.target.querySelector('input[name="option"]:checked')?.value;
                if (!optionId) {
                    alert('Please select an option');
                    return;
                }
                const response = await fetch('poll.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'vote', poll_id: poll.id, option_id: optionId, user_id: userId })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) loadPolls();
            });
        });
    }

    function renderChart(poll) {
        const ctx = document.getElementById(`chart-${poll.id}`).getContext('2d');
        const totalVotes = poll.options.reduce((sum, opt) => sum + opt.votes, 0) || 1;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: poll.options.map(opt => opt.text),
                datasets: [{
                    label: 'Votes (%)',
                    data: poll.options.map(opt => ((opt.votes / totalVotes) * 100).toFixed(1)),
                    backgroundColor: '#007bff'
                }]
            },
            options: { scales: { y: { beginAtZero: true, precision: 0 } } }
        });
    }
});
