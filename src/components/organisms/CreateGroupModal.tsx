import CloseIcon from "@mui/icons-material/Close";
import CheckIcon from "@mui/icons-material/Check";
import GroupsIcon from "@mui/icons-material/Groups";
import { useState } from "react";
import { addDoc, collection } from "firebase/firestore";
import { db } from "../../firebase";

interface ChatUser {
  uid: string;
  name: string;
  email: string;
  photoURL?: string;
}

interface CreateGroupModalProps {
  users: ChatUser[]; // everyone except me, to pick from
  myUid: string;
  onClose: () => void;
  onCreated: (groupId: string) => void; // open the group once it's made
}

export default function CreateGroupModal({
  users,
  myUid,
  onClose,
  onCreated,
}: CreateGroupModalProps) {
  const [name, setName] = useState("");
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [creating, setCreating] = useState(false);
  const [search, setSearch] = useState("");
  const [error, setError] = useState("");

  const toggle = (uid: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(uid)) next.delete(uid);
      else next.add(uid);
      return next;
    });
  };

  const canCreate = name.trim().length > 0 && selected.size > 0 && !creating;

  const createGroup = async () => {
    if (!canCreate) return;
    setCreating(true);
    setError("");
    try {
      // members = me + everyone I picked
      const members = [myUid, ...Array.from(selected)];
      const ref = await addDoc(collection(db, "groups"), {
        name: name.trim(),
        members,
        createdBy: myUid,
        createdAt: Date.now(),
      });
      onCreated(ref.id);
    } catch (err) {
      console.error("Could not create group:", err);
      const message =
        err instanceof Error && err.message.includes("insufficient")
          ? "Permission denied — publish the new Firestore rules in the Firebase Console (Firestore → Rules → Publish)."
          : err instanceof Error
            ? err.message
            : "Something went wrong. Please try again.";
      setError(message);
      setCreating(false);
    }
  };

  const visibleUsers = users.filter((u) =>
    u.name?.toLowerCase().includes(search.toLowerCase())
  );

  return (
    // dim backdrop — clicking it closes the modal
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClose}
    >
      <div
        className="bg-white w-full max-w-md rounded-2xl shadow-xl flex flex-col max-h-[85vh] overflow-hidden"
        onClick={(e) => e.stopPropagation()} // keep clicks inside from closing
      >
        {/* Header */}
        <div className="flex items-center gap-3 px-5 py-4 border-b border-gray-200">
          <div className="h-9 w-9 rounded-full bg-primary flex items-center justify-center text-white shrink-0">
            <GroupsIcon fontSize="small" />
          </div>
          <h2 className="font-semibold text-gray-800 flex-1">New group</h2>
          <button
            onClick={onClose}
            className="p-1 rounded-full text-gray-500 hover:bg-gray-100 cursor-pointer"
          >
            <CloseIcon fontSize="small" />
          </button>
        </div>

        {/* Group name */}
        <div className="px-5 pt-4">
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Group name"
            className="w-full border border-[#ddd] rounded-xl px-4 py-2.5 text-sm outline-primary focus:outline-2"
          />
        </div>

        {/* Member picker */}
        <div className="px-5 pt-4 pb-2">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
              Add members
            </span>
            <span className="text-xs text-gray-400">
              {selected.size} selected
            </span>
          </div>
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search people..."
            className="w-full border border-[#ddd] rounded-xl px-4 py-2 text-sm outline-primary focus:outline-2 mb-2"
          />
        </div>

        {/* Scrollable list of people */}
        <ul className="flex-1 overflow-y-auto px-2 pb-2">
          {visibleUsers.length === 0 ? (
            <li className="text-center text-sm text-gray-400 py-6">
              No people found
            </li>
          ) : (
            visibleUsers.map((u) => {
              const isOn = selected.has(u.uid);
              const initials = u.name
                ?.split(" ")
                .map((w) => w[0])
                .join("")
                .toUpperCase();
              return (
                <li
                  key={u.uid}
                  onClick={() => toggle(u.uid)}
                  className={`flex items-center gap-3 px-3 py-2 rounded-xl cursor-pointer transition-colors ${
                    isOn ? "bg-primary/10" : "hover:bg-gray-50"
                  }`}
                >
                  <div className="h-10 w-10 rounded-full bg-primary overflow-hidden flex items-center justify-center text-white text-sm shrink-0">
                    {u.photoURL ? (
                      <img
                        src={u.photoURL}
                        alt={u.name}
                        referrerPolicy="no-referrer"
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      initials
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-gray-800 truncate">
                      {u.name}
                    </p>
                    <p className="text-xs text-gray-400 truncate">{u.email}</p>
                  </div>
                  {/* checkbox indicator */}
                  <span
                    className={`h-5 w-5 rounded-md border flex items-center justify-center shrink-0 ${
                      isOn
                        ? "bg-primary border-primary text-white"
                        : "border-gray-300"
                    }`}
                  >
                    {isOn && <CheckIcon style={{ fontSize: 14 }} />}
                  </span>
                </li>
              );
            })
          )}
        </ul>

        {/* Error message (e.g. rules not published) */}
        {error && (
          <p className="px-5 pt-3 text-xs text-red-600 leading-relaxed">
            {error}
          </p>
        )}

        {/* Footer actions */}
        <div className="px-5 py-4 border-t border-gray-200 flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            onClick={createGroup}
            disabled={!canCreate}
            className="flex-1 py-2.5 rounded-xl text-sm bg-primary text-white hover:bg-secondary transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {creating ? "Creating..." : "Create group"}
          </button>
        </div>
      </div>
    </div>
  );
}
