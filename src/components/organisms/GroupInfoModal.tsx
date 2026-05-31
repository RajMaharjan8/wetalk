import CloseIcon from "@mui/icons-material/Close";
import CheckIcon from "@mui/icons-material/Check";
import EditIcon from "@mui/icons-material/Edit";
import PersonRemoveIcon from "@mui/icons-material/PersonRemove";
import PersonAddIcon from "@mui/icons-material/PersonAdd";
import GroupsIcon from "@mui/icons-material/Groups";
import { useState } from "react";
import { arrayRemove, arrayUnion, doc, updateDoc } from "firebase/firestore";
import { db } from "../../firebase";

interface ChatUser {
  uid: string;
  name: string;
  email: string;
  photoURL?: string;
}

interface Group {
  id: string;
  name: string;
  members: string[];
  createdBy?: string;
}

interface GroupInfoModalProps {
  group: Group;
  allUsers: ChatUser[]; // everyone, so we can resolve member names + add people
  myUid: string;
  onClose: () => void;
}

function initialsOf(name?: string) {
  return (name ?? "?")
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase();
}

export default function GroupInfoModal({
  group,
  allUsers,
  myUid,
  onClose,
}: GroupInfoModalProps) {
  const isAdmin = group.createdBy === myUid;

  const [editingName, setEditingName] = useState(false);
  const [name, setName] = useState(group.name);
  const [adding, setAdding] = useState(false); // showing the add-people picker
  const [search, setSearch] = useState("");
  const [busy, setBusy] = useState(false);

  // member uid -> user profile (falls back to a stub if not found)
  const memberProfiles = group.members.map(
    (uid) =>
      allUsers.find((u) => u.uid === uid) ?? {
        uid,
        name: "Unknown user",
        email: "",
        photoURL: undefined as string | undefined,
      }
  );

  // people who aren't in the group yet (candidates to add)
  const candidates = allUsers
    .filter((u) => !group.members.includes(u.uid))
    .filter((u) => u.name?.toLowerCase().includes(search.toLowerCase()));

  const saveName = async () => {
    const trimmed = name.trim();
    if (!trimmed || trimmed === group.name) {
      setEditingName(false);
      setName(group.name);
      return;
    }
    setBusy(true);
    try {
      await updateDoc(doc(db, "groups", group.id), { name: trimmed });
      setEditingName(false);
    } catch (error) {
      console.error("Could not rename group:", error);
    } finally {
      setBusy(false);
    }
  };

  const addMember = async (uid: string) => {
    setBusy(true);
    try {
      await updateDoc(doc(db, "groups", group.id), {
        members: arrayUnion(uid),
      });
    } catch (error) {
      console.error("Could not add member:", error);
    } finally {
      setBusy(false);
    }
  };

  const removeMember = async (uid: string) => {
    setBusy(true);
    try {
      await updateDoc(doc(db, "groups", group.id), {
        members: arrayRemove(uid),
      });
    } catch (error) {
      console.error("Could not remove member:", error);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClose}
    >
      <div
        className="bg-white w-full max-w-md rounded-2xl shadow-xl flex flex-col max-h-[85vh] overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center gap-3 px-5 py-4 border-b border-gray-200">
          <div className="h-9 w-9 rounded-full bg-primary flex items-center justify-center text-white shrink-0">
            <GroupsIcon fontSize="small" />
          </div>
          <h2 className="font-semibold text-gray-800 flex-1">Group info</h2>
          <button
            onClick={onClose}
            className="p-1 rounded-full text-gray-500 hover:bg-gray-100 cursor-pointer"
          >
            <CloseIcon fontSize="small" />
          </button>
        </div>

        {/* Name (editable by admin) */}
        <div className="px-5 pt-4">
          <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
            Group name
          </span>
          {editingName ? (
            <div className="flex items-center gap-2 mt-1">
              <input
                type="text"
                value={name}
                autoFocus
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && saveName()}
                className="flex-1 border border-[#ddd] rounded-xl px-3 py-2 text-sm outline-primary focus:outline-2"
              />
              <button
                onClick={saveName}
                disabled={busy}
                className="p-2 rounded-lg bg-primary text-white hover:bg-secondary cursor-pointer disabled:opacity-50"
              >
                <CheckIcon fontSize="small" />
              </button>
            </div>
          ) : (
            <div className="flex items-center gap-2 mt-1">
              <p className="flex-1 text-gray-800 font-medium">{group.name}</p>
              {isAdmin && (
                <button
                  onClick={() => {
                    setName(group.name);
                    setEditingName(true);
                  }}
                  title="Rename group"
                  className="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 cursor-pointer"
                >
                  <EditIcon style={{ fontSize: 18 }} />
                </button>
              )}
            </div>
          )}
        </div>

        {/* Members */}
        <div className="px-5 pt-4 pb-1 flex items-center justify-between">
          <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
            {group.members.length} members
          </span>
          {isAdmin && (
            <button
              onClick={() => setAdding((v) => !v)}
              className="flex items-center gap-1 text-xs text-primary hover:text-secondary font-medium cursor-pointer"
            >
              <PersonAddIcon style={{ fontSize: 18 }} />
              {adding ? "Done" : "Add people"}
            </button>
          )}
        </div>

        {/* Add-people picker (admin only, toggled) */}
        {adding && isAdmin && (
          <div className="px-5 pb-2">
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search people to add..."
              className="w-full border border-[#ddd] rounded-xl px-4 py-2 text-sm outline-primary focus:outline-2 mb-2"
            />
            <ul className="max-h-40 overflow-y-auto">
              {candidates.length === 0 ? (
                <li className="text-center text-xs text-gray-400 py-3">
                  Everyone is already in the group
                </li>
              ) : (
                candidates.map((u) => (
                  <li
                    key={u.uid}
                    className="flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-gray-50"
                  >
                    <div className="h-9 w-9 rounded-full bg-primary overflow-hidden flex items-center justify-center text-white text-xs shrink-0">
                      {u.photoURL ? (
                        <img
                          src={u.photoURL}
                          alt={u.name}
                          referrerPolicy="no-referrer"
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        initialsOf(u.name)
                      )}
                    </div>
                    <span className="text-sm text-gray-800 flex-1 truncate">
                      {u.name}
                    </span>
                    <button
                      onClick={() => addMember(u.uid)}
                      disabled={busy}
                      className="px-3 py-1 rounded-lg text-xs bg-primary text-white hover:bg-secondary cursor-pointer disabled:opacity-50"
                    >
                      Add
                    </button>
                  </li>
                ))
              )}
            </ul>
          </div>
        )}

        {/* Current members list */}
        <ul className="flex-1 overflow-y-auto px-3 pb-3">
          {memberProfiles.map((m) => {
            const isCreator = m.uid === group.createdBy;
            const isMe = m.uid === myUid;
            return (
              <li
                key={m.uid}
                className="flex items-center gap-3 px-2 py-2 rounded-xl hover:bg-gray-50"
              >
                <div className="h-10 w-10 rounded-full bg-primary overflow-hidden flex items-center justify-center text-white text-sm shrink-0">
                  {m.photoURL ? (
                    <img
                      src={m.photoURL}
                      alt={m.name}
                      referrerPolicy="no-referrer"
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    initialsOf(m.name)
                  )}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-gray-800 truncate">
                    {m.name}
                    {isMe && " (You)"}
                  </p>
                  {m.email && (
                    <p className="text-xs text-gray-400 truncate">{m.email}</p>
                  )}
                </div>
                {isCreator ? (
                  <span className="text-[10px] font-semibold text-primary bg-primary/10 px-2 py-1 rounded-full shrink-0">
                    Admin
                  </span>
                ) : (
                  // admin can remove non-admin members
                  isAdmin && (
                    <button
                      onClick={() => removeMember(m.uid)}
                      disabled={busy}
                      title="Remove from group"
                      className="p-1.5 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 cursor-pointer disabled:opacity-50 shrink-0"
                    >
                      <PersonRemoveIcon style={{ fontSize: 18 }} />
                    </button>
                  )
                )}
              </li>
            );
          })}
        </ul>

        {!isAdmin && (
          <p className="px-5 py-3 text-xs text-gray-400 border-t border-gray-100">
            Only the group admin can rename the group or add/remove members.
          </p>
        )}
      </div>
    </div>
  );
}
