const hamburger = document.querySelector(".hamburger");
const navLinks = document.querySelector(".nav-links");
const navItems = document.querySelectorAll(".nav-links a");
const overlay = document.querySelector(".overlay");
const header = document.querySelector(".header");

function isStaticHosting() {
    const host = window.location.hostname;
    return (
        document.documentElement.dataset.staticSite === "true" ||
        window.location.protocol === "file:" ||
        host.endsWith("github.io")
    );
}

function rewriteLinksForStaticHosting() {
    if (!isStaticHosting()) return;

    const pageMap = {
        "index.php": "index.html",
        "login.php": "login.html",
        "forgotPassword.php": "forgotPassword.html",
        "resetPassword.php": "resetPassword.html",
        "dashboard.php": "dashboard.html",
        "logout.php": "login.html"
    };

    document.querySelectorAll("a[href]").forEach(link => {
        const href = link.getAttribute("href");
        if (!href || href.startsWith("http") || href.startsWith("mailto:") || href.startsWith("#")) return;

        const [path, hash = ""] = href.split("#");
        const replacement = pageMap[path];

        if (replacement) {
            link.setAttribute("href", hash ? `${replacement}#${hash}` : replacement);
        }
    });
}

rewriteLinksForStaticHosting();

function setMenuExpanded(isOpen) {
    if (hamburger) {
        hamburger.setAttribute("aria-expanded", String(isOpen));
    }
}

function openMenu() {
    if (!hamburger || !navLinks || !overlay) return;

    hamburger.classList.add("active");
    navLinks.classList.add("show");
    overlay.classList.add("show");
    document.body.classList.add("menu-open");
    setMenuExpanded(true);
}

function closeMenu() {
    if (!hamburger || !navLinks || !overlay) return;

    hamburger.classList.remove("active");
    navLinks.classList.remove("show");
    overlay.classList.remove("show");
    document.body.classList.remove("menu-open");
    setMenuExpanded(false);
}

if (hamburger && navLinks) {
    hamburger.addEventListener("click", () => {
        if (navLinks.classList.contains("show")) {
            closeMenu();
        } else {
            openMenu();
        }
    });
}

navItems.forEach(link => {
    link.addEventListener("click", closeMenu);
});

if (overlay) {
    overlay.addEventListener("click", closeMenu);
}

const reveals = document.querySelectorAll(".reveal");

function revealOnScroll() {
    const windowHeight = window.innerHeight;

    reveals.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;

        if (elementTop < windowHeight - 100) {
            element.classList.add("active");
        }
    });
}

window.addEventListener("scroll", revealOnScroll);
revealOnScroll();

const sections = document.querySelectorAll("section[id]");
const navLinksAll = document.querySelectorAll(".nav-links a[href^='#']");

function setActiveLink() {
    let current = "";

    sections.forEach(section => {
        const sectionTop = section.offsetTop;

        if (window.scrollY >= sectionTop - 140) {
            current = section.getAttribute("id");
        }
    });

    navLinksAll.forEach(link => {
        link.classList.remove("active");

        if (link.getAttribute("href") === "#" + current) {
            link.classList.add("active");
        }
    });
}

window.addEventListener("scroll", setActiveLink);
setActiveLink();

window.addEventListener("scroll", () => {
    if (!header) return;

    if (window.scrollY > 10) {
        header.classList.add("scrolled");
    } else {
        header.classList.remove("scrolled");
    }

    if (navLinks && navLinks.classList.contains("show")) {
        closeMenu();
    }
});

function setStatus(element, text, type) {
    if (!element) return;

    element.textContent = text;
    element.classList.remove("success", "error");

    if (type) {
        element.classList.add(type);
    }
}

function validateContactFields(form, statusElement) {
    const name = form.querySelector("[name='name']")?.value.trim() || "";
    const email = form.querySelector("[name='email']")?.value.trim() || "";
    const message = form.querySelector("[name='message']")?.value.trim() || "";
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!name || !email || !message) {
        setStatus(statusElement, "Please fill in your name, email, and message.", "error");
        return false;
    }

    if (!emailPattern.test(email)) {
        setStatus(statusElement, "Please enter a valid email address.", "error");
        return false;
    }

    return true;
}

async function sendContactForm(form, statusElement, options = {}) {
    const { resetOnSuccess = true, formData = null, skipValidation = false } = options;

    setStatus(statusElement, "", "");

    if (!skipValidation && !validateContactFields(form, statusElement)) {
        return { success: false };
    }

    const submitButton = form.querySelector("button[type='submit']");
    const originalText = submitButton ? submitButton.textContent : "";

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Sending...";
    }

    if (isStaticHosting()) {
        const storedMessages = readStoredJson("silm_static_messages", []);
        const payload = formData || new FormData(form);

        storedMessages.push({
            name: payload.get("name") || "",
            email: payload.get("email") || "",
            topic: payload.get("topic") || "",
            message: payload.get("message") || "",
            sentAt: new Date().toISOString()
        });

        writeStoredJson("silm_static_messages", storedMessages.slice(-30));
        setStatus(statusElement, "Saved in this static GitHub Pages preview. Connect PHP hosting or a form service to receive it live.", "success");

        if (resetOnSuccess) {
            form.reset();
        }

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }

        return { success: true, data: { static: true } };
    }

    try {
        const response = await fetch(form.action, {
            method: "POST",
            body: formData || new FormData(form),
            headers: {
                Accept: "application/json",
                "X-Requested-With": "fetch"
            }
        });

        const contentType = response.headers.get("content-type") || "";
        const data = contentType.includes("application/json")
            ? await response.json()
            : { success: false, message: "The server returned an unexpected response." };

        if (!response.ok || !data.success) {
            throw new Error(data.message || "Message could not be sent.");
        }

        setStatus(statusElement, data.message || "Message sent successfully. We will get back to you soon.", "success");

        if (resetOnSuccess) {
            form.reset();
        }

        return { success: true, data };
    } catch (error) {
        setStatus(statusElement, error.message || "Message could not be sent. Please try again.", "error");
        return { success: false, error };
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }
}

document.querySelectorAll("[data-async-contact]").forEach(form => {
    form.addEventListener("submit", async event => {
        event.preventDefault();
        await sendContactForm(form, form.querySelector(".form-message"));
    });
});

document.querySelectorAll("[data-static-form]").forEach(form => {
    form.addEventListener("submit", event => {
        if (!isStaticHosting()) return;

        event.preventDefault();

        const statusElement = form.querySelector(".form-message");
        const formType = form.dataset.staticForm || "form";
        const name = form.querySelector("[name='name']")?.value.trim() || "there";

        if (formType === "account") {
            writeStoredJson("silm_static_account", {
                name,
                email: form.querySelector("[name='email']")?.value.trim() || "",
                createdAt: new Date().toISOString()
            });
            setStatus(statusElement, "Static preview account created. Real accounts need PHP hosting and a database.", "success");
            return;
        }

        if (formType === "login") {
            setStatus(statusElement, "Static preview login opened. Real login needs PHP hosting and a database.", "success");
            return;
        }

        if (formType === "forgot") {
            setStatus(statusElement, "Static preview only. Password reset needs PHP hosting and email support.", "success");
            return;
        }

        if (formType === "reset") {
            setStatus(statusElement, "Static preview only. Password updates need PHP hosting and a database.", "success");
        }
    });
});

const chatPanel = document.querySelector(".chat-panel");
const chatLaunchers = document.querySelectorAll("[data-open-chat]");
const chatClose = document.querySelector(".chat-close");
const chatClear = document.querySelector("[data-clear-chat]");
const chatThread = document.querySelector("[data-chat-thread]");
const chatForm = document.querySelector("[data-chat-form]");
const chatStatus = document.querySelector(".chat-status");
const chatLauncherButton = document.querySelector(".chat-launcher");
const chatMessageInput = chatForm?.querySelector("[name='message']");
const chatNameInput = chatForm?.querySelector("[name='name']");
const chatEmailInput = chatForm?.querySelector("[name='email']");
const chatTopicInput = chatForm?.querySelector("[name='topic']");
const chatConversationInput = chatForm?.querySelector("[name='conversation_id']");
const chatFileInput = document.querySelector("[data-chat-file]");
const chatAttachButton = document.querySelector("[data-attach-media]");
const chatRecordButton = document.querySelector("[data-record-voice]");
const chatAttachmentPreview = document.querySelector("[data-attachment-preview]");

const chatStorageKeys = {
    messages: "silm_chat_messages",
    profile: "silm_chat_profile",
    conversationId: "silm_chat_conversation_id"
};

const maxStoredMediaBytes = 4 * 1024 * 1024;
let pendingAttachment = null;
let pendingUploadFile = null;
let mediaRecorder = null;
let recordedChunks = [];
let activeRecordingStream = null;

function storageAvailable() {
    try {
        const testKey = "__silm_storage_test__";
        localStorage.setItem(testKey, testKey);
        localStorage.removeItem(testKey);
        return true;
    } catch (error) {
        return false;
    }
}

const canStoreChat = storageAvailable();

function readStoredJson(key, fallback) {
    if (!canStoreChat) return fallback;

    try {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : fallback;
    } catch (error) {
        return fallback;
    }
}

function writeStoredJson(key, value) {
    if (!canStoreChat) return;

    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
        setStatus(chatStatus, "This browser could not store the whole media conversation. Smaller files will work better.", "error");
    }
}

function createConversationId() {
    const randomPart = Math.random().toString(36).slice(2, 10);
    return `silm-${Date.now().toString(36)}-${randomPart}`;
}

function getConversationId() {
    if (!canStoreChat) return createConversationId();

    let conversationId = localStorage.getItem(chatStorageKeys.conversationId);

    if (!conversationId) {
        conversationId = createConversationId();
        localStorage.setItem(chatStorageKeys.conversationId, conversationId);
    }

    return conversationId;
}

function setConversationId(conversationId) {
    if (chatConversationInput) {
        chatConversationInput.value = conversationId;
    }

    if (canStoreChat) {
        localStorage.setItem(chatStorageKeys.conversationId, conversationId);
    }
}

function loadChatMessages() {
    return readStoredJson(chatStorageKeys.messages, []);
}

function saveChatMessages(messages) {
    writeStoredJson(chatStorageKeys.messages, messages.slice(-60));
}

function formatMessageTime(timestamp) {
    const date = timestamp ? new Date(timestamp) : new Date();

    if (Number.isNaN(date.getTime())) {
        return "";
    }

    return date.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit"
    });
}

function statusLabel(status) {
    if (status === "sending") return "Sending";
    if (status === "failed") return "Not sent";
    if (status === "received") return "Received";
    return "Sent";
}

function mediaLabel(media) {
    if (!media) return "";
    if (media.kind === "video") return "Video";
    if (media.kind === "voice") return "Voice";
    return "Audio";
}

function renderMediaContent(media) {
    const mediaWrapper = document.createElement("div");
    mediaWrapper.className = `chat-media chat-media-${media.kind}`;

    const mediaTitle = document.createElement("span");
    mediaTitle.className = "chat-media-title";
    mediaTitle.textContent = media.name || mediaLabel(media);
    mediaWrapper.appendChild(mediaTitle);

    if (media.kind === "video") {
        const video = document.createElement("video");
        video.controls = true;
        video.src = media.dataUrl;
        video.preload = "metadata";
        mediaWrapper.appendChild(video);
    } else {
        const audio = document.createElement("audio");
        audio.controls = true;
        audio.src = media.dataUrl;
        audio.preload = "metadata";
        mediaWrapper.appendChild(audio);
    }

    return mediaWrapper;
}

function renderChatMessage(message) {
    if (!chatThread) return;

    const row = document.createElement("div");
    row.className = `chat-message chat-message-${message.type}`;
    row.dataset.messageId = message.id;

    const bubble = document.createElement("div");
    bubble.className = `chat-bubble chat-bubble-${message.type}`;

    if (message.media) {
        bubble.appendChild(renderMediaContent(message.media));
    }

    if (message.text) {
        const text = document.createElement("p");
        text.className = "chat-message-text";
        text.textContent = message.text;
        bubble.appendChild(text);
    }

    const meta = document.createElement("span");
    meta.className = "chat-meta";
    meta.textContent = [formatMessageTime(message.timestamp), statusLabel(message.status)]
        .filter(Boolean)
        .join(" - ");

    row.appendChild(bubble);
    row.appendChild(meta);
    chatThread.appendChild(row);
}

function renderChatHistory() {
    if (!chatThread) return;

    const messages = loadChatMessages();
    chatThread.innerHTML = "";

    if (messages.length === 0) {
        renderChatMessage({
            id: "welcome",
            type: "agent",
            text: "Hi, welcome to Silm. Send text, a voice note, audio, or video. Your messages appear on the right; replies appear on the left.",
            timestamp: new Date().toISOString(),
            status: "received"
        });
    } else {
        messages.forEach(renderChatMessage);
    }

    chatThread.scrollTop = chatThread.scrollHeight;
}

function appendChatMessage(type, text, options = {}) {
    const message = {
        id: options.id || `msg-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        type,
        text,
        media: options.media || null,
        timestamp: options.timestamp || new Date().toISOString(),
        status: options.status || (type === "user" ? "sent" : "received")
    };

    if (options.save !== false) {
        const messages = loadChatMessages();
        messages.push(message);
        saveChatMessages(messages);
    }

    if (chatThread) {
        if (message.id !== "welcome" && chatThread.querySelector("[data-message-id='welcome']")) {
            chatThread.innerHTML = "";
            const savedMessages = loadChatMessages();

            if (savedMessages.length > 0) {
                savedMessages.forEach(renderChatMessage);
            } else {
                renderChatMessage(message);
            }
        } else {
            renderChatMessage(message);
        }

        chatThread.scrollTop = chatThread.scrollHeight;
    }

    return message;
}

function updateStoredMessageStatus(messageId, status) {
    const messages = loadChatMessages();
    const message = messages.find(item => item.id === messageId);

    if (!message) return;

    message.status = status;
    saveChatMessages(messages);
    renderChatHistory();
}

function restoreChatProfile() {
    const profile = readStoredJson(chatStorageKeys.profile, {});

    if (chatNameInput && profile.name) {
        chatNameInput.value = profile.name;
    }

    if (chatEmailInput && profile.email) {
        chatEmailInput.value = profile.email;
    }

    if (chatTopicInput && profile.topic) {
        chatTopicInput.value = profile.topic;
    }
}

function saveChatProfile() {
    writeStoredJson(chatStorageKeys.profile, {
        name: chatNameInput?.value.trim() || "",
        email: chatEmailInput?.value.trim() || "",
        topic: chatTopicInput?.value || "General project"
    });
}

function validateChatFields() {
    const name = chatNameInput?.value.trim() || "";
    const email = chatEmailInput?.value.trim() || "";
    const text = chatMessageInput?.value.trim() || "";
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!name || !email) {
        setStatus(chatStatus, "Please enter your name and email first.", "error");
        return false;
    }

    if (!emailPattern.test(email)) {
        setStatus(chatStatus, "Please enter a valid email address.", "error");
        return false;
    }

    if (!text && !pendingAttachment) {
        setStatus(chatStatus, "Type a message, attach audio/video, or record a voice note.", "error");
        return false;
    }

    return true;
}

function attachmentKindFromMime(mimeType, fallback = "audio") {
    if (mimeType.startsWith("video/")) return "video";
    if (fallback === "voice") return "voice";
    return "audio";
}

function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(blob);
    });
}

async function createAttachmentFromBlob(blob, name, fallbackKind = "audio") {
    if (blob.size > maxStoredMediaBytes) {
        throw new Error("Please choose a media file under 4 MB so it can stay in the chat.");
    }

    const dataUrl = await blobToDataUrl(blob);
    const mime = blob.type || "audio/webm";

    return {
        kind: attachmentKindFromMime(mime, fallbackKind),
        name,
        mime,
        size: blob.size,
        dataUrl
    };
}

function clearPendingAttachment() {
    pendingAttachment = null;
    pendingUploadFile = null;

    if (chatFileInput) {
        chatFileInput.value = "";
    }

    if (chatAttachmentPreview) {
        chatAttachmentPreview.hidden = true;
        chatAttachmentPreview.innerHTML = "";
    }
}

function renderAttachmentPreview() {
    if (!chatAttachmentPreview || !pendingAttachment) return;

    chatAttachmentPreview.hidden = false;
    chatAttachmentPreview.innerHTML = "";

    const details = document.createElement("div");
    details.className = "chat-attachment-details";

    const title = document.createElement("strong");
    title.textContent = `${mediaLabel(pendingAttachment)} ready`;

    const name = document.createElement("span");
    name.textContent = pendingAttachment.name;

    details.appendChild(title);
    details.appendChild(name);

    const removeButton = document.createElement("button");
    removeButton.type = "button";
    removeButton.textContent = "Remove";
    removeButton.addEventListener("click", clearPendingAttachment);

    chatAttachmentPreview.appendChild(details);
    chatAttachmentPreview.appendChild(removeButton);
}

function buildMessageForServer(text, media) {
    if (!media) return text;

    const mediaText = `[${mediaLabel(media)} message: ${media.name}]`;
    return text ? `${text}\n\n${mediaText}` : mediaText;
}

function addMediaFields(formData, media) {
    if (!media) return;

    formData.set("media_type", media.kind);
    formData.set("media_name", media.name);
    formData.set("media_size", String(media.size || 0));

    if (pendingUploadFile) {
        formData.set("media_file", pendingUploadFile, pendingUploadFile.name);
    }
}

function openChat() {
    if (!chatPanel) return;

    chatPanel.hidden = false;
    chatLaunchers.forEach(button => button.setAttribute("aria-expanded", "true"));
    setTimeout(() => chatMessageInput?.focus(), 50);
}

function closeChat() {
    if (!chatPanel) return;

    chatPanel.hidden = true;
    chatLaunchers.forEach(button => button.setAttribute("aria-expanded", "false"));
    chatLauncherButton?.focus();
}

chatLaunchers.forEach(button => {
    button.addEventListener("click", event => {
        event.preventDefault();
        openChat();
    });
});

if (chatClose) {
    chatClose.addEventListener("click", closeChat);
}

if (chatClear) {
    chatClear.addEventListener("click", () => {
        saveChatMessages([]);
        setConversationId(createConversationId());
        clearPendingAttachment();
        renderChatHistory();
        setStatus(chatStatus, "Conversation cleared.", "success");
    });
}

if (chatAttachButton && chatFileInput) {
    chatAttachButton.addEventListener("click", () => {
        chatFileInput.click();
    });

    chatFileInput.addEventListener("change", async () => {
        const file = chatFileInput.files?.[0];
        if (!file) return;

        try {
            if (!file.type.startsWith("audio/") && !file.type.startsWith("video/")) {
                throw new Error("Please choose an audio or video file.");
            }

            pendingAttachment = await createAttachmentFromBlob(file, file.name);
            pendingUploadFile = file;
            renderAttachmentPreview();
            setStatus(chatStatus, "", "");
        } catch (error) {
            clearPendingAttachment();
            setStatus(chatStatus, error.message || "We could not attach that file.", "error");
        }
    });
}

async function stopVoiceRecording() {
    if (mediaRecorder && mediaRecorder.state !== "inactive") {
        mediaRecorder.stop();
    }
}

async function startVoiceRecording() {
    if (!navigator.mediaDevices || !window.MediaRecorder) {
        setStatus(chatStatus, "Voice recording is not supported in this browser.", "error");
        return;
    }

    try {
        activeRecordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        recordedChunks = [];
        mediaRecorder = new MediaRecorder(activeRecordingStream);

        mediaRecorder.addEventListener("dataavailable", event => {
            if (event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        });

        mediaRecorder.addEventListener("stop", async () => {
            const mime = mediaRecorder.mimeType || "audio/webm";
            const voiceBlob = new Blob(recordedChunks, { type: mime });
            const voiceFile = new File([voiceBlob], `voice-${Date.now()}.webm`, { type: mime });

            activeRecordingStream?.getTracks().forEach(track => track.stop());
            activeRecordingStream = null;

            if (chatRecordButton) {
                chatRecordButton.classList.remove("recording");
                chatRecordButton.textContent = "Mic";
            }

            try {
                pendingAttachment = await createAttachmentFromBlob(voiceBlob, "Voice message", "voice");
                pendingUploadFile = voiceFile;
                renderAttachmentPreview();
                setStatus(chatStatus, "Voice message ready to send.", "success");
            } catch (error) {
                setStatus(chatStatus, error.message || "We could not save the voice message.", "error");
            }
        });

        mediaRecorder.start();

        if (chatRecordButton) {
            chatRecordButton.classList.add("recording");
            chatRecordButton.textContent = "Stop";
        }

        setStatus(chatStatus, "Recording voice message...", "success");
    } catch (error) {
        setStatus(chatStatus, "Microphone access was not allowed.", "error");
    }
}

if (chatRecordButton) {
    chatRecordButton.addEventListener("click", () => {
        if (mediaRecorder && mediaRecorder.state === "recording") {
            stopVoiceRecording();
        } else {
            startVoiceRecording();
        }
    });
}

document.querySelectorAll("[data-chat-prompt]").forEach(button => {
    button.addEventListener("click", () => {
        if (!chatMessageInput) return;

        openChat();
        chatMessageInput.value = button.dataset.chatPrompt || "";
        chatMessageInput.focus();
    });
});

if (chatForm) {
    setConversationId(getConversationId());
    restoreChatProfile();
    renderChatHistory();

    chatForm.addEventListener("submit", async event => {
        event.preventDefault();

        const userMessage = chatMessageInput?.value.trim() || "";

        if (!validateChatFields()) return;

        saveChatProfile();

        const attachmentToSend = pendingAttachment;
        const formData = new FormData(chatForm);
        formData.set("message", buildMessageForServer(userMessage, attachmentToSend));
        addMediaFields(formData, attachmentToSend);

        const outgoingMessage = appendChatMessage("user", userMessage, {
            media: attachmentToSend,
            status: "sending"
        });

        if (chatMessageInput) {
            chatMessageInput.value = "";
        }

        clearPendingAttachment();

        const result = await sendContactForm(chatForm, chatStatus, {
            resetOnSuccess: false,
            formData,
            skipValidation: true
        });

        if (!result.success) {
            updateStoredMessageStatus(outgoingMessage.id, "failed");
            return;
        }

        updateStoredMessageStatus(outgoingMessage.id, "sent");
        appendChatMessage(
            "agent",
            attachmentToSend
                ? `Received your ${mediaLabel(attachmentToSend).toLowerCase()} message. Silm will reply through the email you provided.`
                : "Thanks. We received your message in this conversation. Silm will reply through the email you provided.",
            { status: "received" }
        );

        if (chatMessageInput) {
            chatMessageInput.focus();
        }
    });

    chatMessageInput?.addEventListener("keydown", event => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            chatForm.requestSubmit();
        }
    });
}

if (window.location.hash === "#message") {
    openChat();
}

document.addEventListener("keydown", event => {
    if (event.key !== "Escape") return;

    closeMenu();
    closeChat();
});
