import * as React from "react";
import { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../functions/translations";

let translations: any = getTranslations();

export type ArRefInputState = {
  isNew: boolean;
  value: string;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
};

export const ArRefInput = React.forwardRef(
  ({ isNew, value, onChange }: ArRefInputState, ref) => {
    useImperativeHandle(ref, () => ({
      isValid(): boolean {
        return false;
      },
    }));

    return (
      <>
        <label htmlFor={"_sage_arRef"}>
          <Tooltip title={"arRef"} arrow>
            <span>{translations["fArticles"]["arRef"]}</span>
          </Tooltip>
        </label>
        <input
          id={"_sage_arRef"}
          name={"_sage_arRef"}
          type={"text"}
          value={value}
          readOnly={!isNew}
          onChange={onChange}
          style={{ width: "100%" }}
        />
      </>
    );
  },
);
